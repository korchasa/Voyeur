package voyeur

import (
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"

	"github.com/posener/wstest"
	"github.com/stretchr/testify/assert"
)

type httpbinOrgMessage struct {
	Data     string                  `json:"data"`
	JSONData httpbinOrgJSONDataField `json:"json"`
	Method   string                  `json:"method"`
	URL      string                  `json:"url"`
}

type httpbinOrgJSONDataField struct {
	Foo string `json:"foo"`
}

func TestHandleStatus(t *testing.T) {

	req, err := http.NewRequest("GET", "/voyeur/status", nil)
	if err != nil {
		t.Fatal(err)
	}

	rr := httptest.NewRecorder()
	handler := http.HandlerFunc(handleStatus)
	handler.ServeHTTP(rr, req)

	assert.Equal(t, http.StatusOK, rr.Code, "handler returned wrong status code")
	assert.Equal(t, `{"Status":"ok"}`, rr.Body.String(), "handler returned unexpected body")
}

func TestHandleProxyRequest(t *testing.T) {

	req, err := http.NewRequest("GET", "http://httpbin.org/anything", strings.NewReader(`{"foo": "bar"}`))
	if err != nil {
		t.Fatal(err)
	}

	rr := httptest.NewRecorder()
	handler := http.HandlerFunc(handleProxyRequest)
	handler.ServeHTTP(rr, req)

	assert.Equal(t, http.StatusOK, rr.Code, "handler returned wrong status code")
	actual := parseHttpbinOrg(rr.Body.String())
	assert.Equal(t, "GET", actual.Method)
	assert.Equal(t, "http://httpbin.org/anything", actual.URL)
	assert.Equal(t, "{\"foo\": \"bar\"}", actual.Data)
	assert.Equal(t, httpbinOrgJSONDataField{Foo: "bar"}, actual.JSONData)
}

func TestHandleWebSocket(t *testing.T) {

	handler := http.HandlerFunc(handleWebSocket)
	d := wstest.NewDialer(handler, nil)

	c, resp, err := d.Dial("ws://voyeur/echo", nil)
	if err != nil {
		t.Fatal(err)
	}

	assert.Equal(t, http.StatusSwitchingProtocols, resp.StatusCode, "handler returned wrong status code")

	err = c.WriteJSON("test")
	if err != nil {
		t.Fatal(err)
	}
}

func parseHttpbinOrg(body string) httpbinOrgMessage {
	var response httpbinOrgMessage
	json.Unmarshal([]byte(body), &response)
	return response
}
