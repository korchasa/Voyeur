package main

import (
	"encoding/json"
	"flag"
	"fmt"
	"io"
	"net"
	"net/http"
	"os"
	"strconv"

	"github.com/lumanetworks/go-tcp-proxy"
	"golang.org/x/net/websocket"
)

var (
	matchid = uint64(0)
	connid  = uint64(0)
	logger  proxy.ColorLogger

	remoteAddr  = flag.String("r", "localhost:80", "remote address")
	localAddr   = flag.String("l", ":9999", "local address")
	uiPort      = flag.Int("ui", 8888, "ui port")
	verbose     = flag.Bool("v", false, "display server actions")
	veryverbose = flag.Bool("vv", false, "display server actions and all tcp data")
	nagles      = flag.Bool("n", false, "disable nagles algorithm")
	hex         = flag.Bool("h", false, "output hex")
	colors      = flag.Bool("c", false, "output ansi colors")
	unwrapTLS   = flag.Bool("unwrap-tls", false, "remote connection with TLS exposed unencrypted locally")
	staticDir   = flag.String("static", "static", "static dir")
)

func main() {
	flag.Parse()

	logger := proxy.ColorLogger{
		Verbose: *verbose,
		Color:   *colors,
	}

	logger.Info("Proxying from %v to %v", *localAddr, *remoteAddr)

	laddr, err := net.ResolveTCPAddr("tcp", *localAddr)
	if err != nil {
		logger.Warn("Failed to resolve local address: %s", err)
		os.Exit(1)
	}
	raddr, err := net.ResolveTCPAddr("tcp", *remoteAddr)
	if err != nil {
		logger.Warn("Failed to resolve remote address: %s", err)
		os.Exit(1)
	}

	if *veryverbose {
		*verbose = true
	}

	go acceptUI(*uiPort)
	acceptProxy(laddr, raddr)
}

func acceptUI(port int) {
	addr := "localhost:" + strconv.Itoa(port)
	http.Handle("/", http.FileServer(http.Dir("static")))
	http.HandleFunc("/status", func(w http.ResponseWriter, r *http.Request) {
		message := struct{ Status string }{"ok"}
		js, err := json.Marshal(message)
		if err != nil {
			http.Error(w, err.Error(), http.StatusInternalServerError)
			return
		}
		w.Header().Set("Content-Type", "application/json")
		w.Write(js)
	})
	http.Handle("/echo", websocket.Handler(func(ws *websocket.Conn) {
		io.Copy(ws, ws)
	}))
	logger.Info("UI ready to serve on http://%v/", addr)
	http.ListenAndServe(addr, nil)
}

func acceptProxy(laddr *net.TCPAddr, raddr *net.TCPAddr) {

	listener, err := net.ListenTCP("tcp", laddr)
	if err != nil {
		logger.Warn("Failed to open local port to listen: %s", err)
		os.Exit(1)
	}

	for {
		conn, err := listener.AcceptTCP()
		if err != nil {
			logger.Warn("Failed to accept connection '%s'", err)
			continue
		}
		connid++

		var p *proxy.Proxy
		if *unwrapTLS {
			logger.Info("Unwrapping TLS")
			p = proxy.NewTLSUnwrapped(conn, laddr, raddr, *remoteAddr)
		} else {
			p = proxy.New(conn, laddr, raddr)
		}

		p.Nagles = *nagles
		p.OutputHex = *hex
		p.Log = proxy.ColorLogger{
			Verbose:     *verbose,
			VeryVerbose: *veryverbose,
			Prefix:      fmt.Sprintf("Connection #%03d ", connid),
			Color:       *colors,
		}

		go p.Start()
	}
}
