package voyeur

import (
	"bytes"
	"encoding/json"
	"flag"
	"fmt"
	"io/ioutil"
	"log"
	"net/http"
	"net/http/httputil"
	"time"

	"github.com/gorilla/websocket"
)

const (
	// Time allowed to write a message to the peer.
	writeWait = 10 * time.Second
	// Time allowed to read the next pong message from the peer.
	pongWait = 60 * time.Second
	// Send pings to peer with this period. Must be less than pongWait.
	pingPeriod = (pongWait * 9) / 10
	// Maximum message size allowed from peer.
	maxMessageSize = 512
)

var (
	localAddr = flag.String("l", ":8080", "local address")
	requests  = make(chan RequestMessage, 10)
	responses = make(chan ResponseMessage, 10)
	quit      = make(chan bool, 2)
	wsConns   = make(map[*websocket.Conn]bool)
	requestID = 0
)

func main() {
	flag.Parse()

	http.HandleFunc("/favicon.ico", func(w http.ResponseWriter, r *http.Request) {})
	http.Handle("/voyeur/", http.StripPrefix("/voyeur/", http.FileServer(http.Dir("static"))))
	http.HandleFunc("/voyeur/status", handleStatus)
	http.HandleFunc("/voyeur/echo", handleWebSocket)
	http.HandleFunc("/", handleProxyRequest)

	go processRequestsAndResponsesMessages()

	err := http.ListenAndServe(*localAddr, nil)
	if err != nil {
		log.Fatal("ListenAndServe: ", err)
	} else {
		log.Printf("Listen on %s", *localAddr)
	}
}

// RequestMessage -
type RequestMessage struct {
	TraceID     int
	Timestamp   int64
	Sender      string
	Destination string
	RawContent  string
	Body        string
}

// ResponseMessage -
type ResponseMessage struct {
	TraceID    int
	Timestamp  int64
	RawContent string
	Body       string
}

func handleStatus(w http.ResponseWriter, r *http.Request) {
	message := struct{ Status string }{"ok"}
	js, err := json.Marshal(message)
	if err != nil {
		http.Error(w, err.Error(), http.StatusInternalServerError)
		return
	}
	w.Header().Set("Content-Type", "application/json")
	w.Write(js)
}

func handleWebSocket(w http.ResponseWriter, r *http.Request) {
	upgrader := websocket.Upgrader{}
	conn, err := upgrader.Upgrade(w, r, nil)
	if err != nil {
		log.Println(err)
		return
	}
	defer func() {
		delete(wsConns, conn)
		conn.Close()
	}()
	wsConns[conn] = true
	messageType, msg, err := conn.ReadMessage()
	if err != nil {
		log.Println(err)
	}
	log.Printf("Read from ws %d: %s\n", messageType, msg)
}

func processRequestsAndResponsesMessages() {
	for {
		select {
		case req := <-requests:
			for ws := range wsConns {
				err := ws.WriteJSON(req)
				if err != nil {
					log.Println("Can't write request to websocket: ", err)
				}
			}
		case resp := <-responses:
			for ws := range wsConns {
				err := ws.WriteJSON(resp)
				if err != nil {
					log.Println("Can't write response to websocket: ", err)
				}
			}
		case <-quit:
			fmt.Println("quit")
			return
		}
	}
}

func handleProxyRequest(w http.ResponseWriter, req *http.Request) {

	requests <- processRequest(req)

	client := &http.Client{Timeout: time.Second * 10}
	resp, err := client.Do(req)
	if err != nil {
		log.Fatal("Error on resend: ", err)
	}

	response := processResponse(resp)

	for name, value := range resp.Header {
		w.Header().Set(name, value[0])
	}

	body, err := ioutil.ReadAll(resp.Body)
	if err != nil {
		log.Fatal("Error on read body from local response: ", err)
	}
	_, err = w.Write(body)
	if err != nil {
		log.Fatal("Error on write body to remote response: ", err)
	}

	responses <- response

	requestID++
}

func processRequest(req *http.Request) RequestMessage {

	fmt.Printf("Request from %s to %s\n", req.RemoteAddr, req.Host)

	reqDump, err := httputil.DumpRequest(req, true)
	if err != nil {
		log.Println("Can't dump request: ", err)
	}

	buf, _ := ioutil.ReadAll(req.Body)
	if err != nil {
		log.Println("Can't read body: ", err)
	}
	bodyReader := ioutil.NopCloser(bytes.NewBuffer(buf))
	req.Body = ioutil.NopCloser(bytes.NewBuffer(buf))
	body, _ := ioutil.ReadAll(bodyReader)

	req.RequestURI = ""
	if req.URL.Scheme == "" {
		req.URL.Scheme = "http"
	}
	if req.URL.Host == "" {
		req.URL.Host = req.Host
	}

	return RequestMessage{
		TraceID:     requestID,
		Timestamp:   makeTimestamp(),
		Sender:      req.RemoteAddr,
		Destination: req.Host,
		RawContent:  string(reqDump),
		Body:        string(body),
	}
}

func processResponse(resp *http.Response) ResponseMessage {
	responseDump, err := httputil.DumpResponse(resp, true)
	if err != nil {
		log.Println("Can't dump response: ", err)
	}

	buf, _ := ioutil.ReadAll(resp.Body)
	if err != nil {
		log.Println("Can't read body: ", err)
	}
	bodyReader := ioutil.NopCloser(bytes.NewBuffer(buf))
	resp.Body = ioutil.NopCloser(bytes.NewBuffer(buf))
	body, _ := ioutil.ReadAll(bodyReader)

	return ResponseMessage{
		TraceID:    requestID,
		Timestamp:  makeTimestamp(),
		RawContent: string(responseDump),
		Body:       string(body),
	}
}

func makeTimestamp() int64 {
	return time.Now().UnixNano() / (int64(time.Millisecond))
}
