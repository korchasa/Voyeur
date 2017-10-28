# voyeur

[![Docker Build Statu](https://img.shields.io/docker/build/korchasa/voyeur.svg?style=flat-square)](https://hub.docker.com/r/korchasa/voyeur/)
[![](https://images.microbadger.com/badges/image/korchasa/voyeur.svg)](https://microbadger.com/images/korchasa/voyeur "Get your own image badge on microbadger.com")

Monitor HTTP requests between docker containers without pain

Just add ``korchasa/voyeur`` container and replace receiver with vouyeur container by link alias.

docker-compose.yml

```yml
sender:
  image: ...
  links:
    - voyeur:receiver

receiver:
  image: ...

voyeur:
  image: korchasa/voyeur:latest
  ports:
    - "8080:80"
  links:
    - receiver
```