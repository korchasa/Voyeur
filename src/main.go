package main

import (
	"encoding/json"
	"flag"
	"log"
	"net/http"

	"golang.org/x/net/websocket"
)

var (
	localAddr = flag.String("l", ":9999", "local address")
)

func main() {
	flag.Parse()

	messages := make(chan []byte)
	wsConns := make(map[string]*websocket.Conn)

	http.HandleFunc("/favicon.ico", func(w http.ResponseWriter, r *http.Request) {})

	http.Handle("/voyeur/", http.StripPrefix("/voyeur/", http.FileServer(http.Dir("static"))))

	http.HandleFunc("/voyeur/status", func(w http.ResponseWriter, r *http.Request) {
		message := struct{ Status string }{"ok"}
		js, err := json.Marshal(message)
		if err != nil {
			http.Error(w, err.Error(), http.StatusInternalServerError)
			return
		}
		w.Header().Set("Content-Type", "application/json")
		w.Write(js)
	})

	http.Handle("/voyeur/echo", websocket.Handler(func(ws *websocket.Conn) {
		defer func() {
			err := ws.Close()
			if err != nil {
				log.Fatal("Websocket conn: ", err)
			}
		}()

		log.Printf("New websocker client connected from %s", ws.RemoteAddr().String())

		wsConns[ws.RemoteAddr().String()] = ws
	}))

	go func() {
		msg := <-messages
		for _, c := range wsConns {
			_, err := c.Write(msg)
			if err != nil {
				log.Fatal("Error on websocket send: ", err)
			}
		}
	}()

	http.HandleFunc("/", func(w http.ResponseWriter, r *http.Request) {
		log.Printf("Handle request to %s", r.URL)
		go func() { messages <- []byte(r.URL.Host) }()
		r.RequestURI = ""
		resp, err := http.DefaultClient.Do(r)
		if err != nil {
			log.Fatal("Error on resend: ", err)
		}
		resp.Write(w)
	})

	log.Printf("Listen on %s", *localAddr)
	err := http.ListenAndServe(*localAddr, nil)
	if err != nil {
		log.Fatal("ListenAndServe: ", err)
	}
}
