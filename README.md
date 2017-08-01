# voyeur
Monitor HTTP requests between docker containers without pain

docker-compose.yml
```

sender:
  image: ...
  links:
    - voyeur:receiver
  
receiver:
  image: ...

voyeur:
  image: voyeur      
  ports:
    - "12345:12345"
  links:
    - receiver
