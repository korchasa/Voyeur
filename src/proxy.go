package main

import (
	"fmt"
	"log"
	"net/http"
	"net/http/httputil"
)

func main() {
	http.HandleFunc("/", func(w http.ResponseWriter, r *http.Request) {
		director := func(req *http.Request) {
			req = r
			req.URL.Scheme = "https"
			req.URL.Host = r.Host
		}
		fmt.Printf("request to %s", r.Host)
		proxy := &httputil.ReverseProxy{Director: director}
		proxy.ServeHTTP(w, r)
	})
	fmt.Printf("Listen on http://127.0.0.1:8080\n")
	log.Fatal(http.ListenAndServe(":8080", nil))
}
