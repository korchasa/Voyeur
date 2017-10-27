FROM golang:1.9.0-alpine3.6 as builder
WORKDIR /go/src/github.com/korchasa/voyeur
COPY . .
RUN echo "http://dl-4.alpinelinux.org/alpine/edge/community" >> /etc/apk/repositories
RUN apk update
RUN apk add git
RUN go get -u github.com/golang/dep/cmd/dep
RUN dep ensure
RUN go test
RUN CGO_ENABLED=0 GOOS=linux go build -o ./voyeur

FROM busybox:1.27.2
COPY --from=builder /go/src/github.com/korchasa/voyeur/voyeur /voyeur
COPY --from=builder /go/src/github.com/korchasa/voyeur/static /static
RUN chmod a+x /voyeur
EXPOSE 80
CMD ["/voyeur", "-l", ":80"]