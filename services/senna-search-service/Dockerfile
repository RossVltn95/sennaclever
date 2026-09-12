FROM searxng/searxng:latest

COPY settings.yml /etc/searxng/settings.yml
COPY limiter.toml /etc/searxng/limiter.toml

ENV SEARXNG_BIND_ADDRESS=0.0.0.0
ENV SEARXNG_PORT=8080
ENV SEARXNG_SETTINGS_PATH=/etc/searxng/settings.yml

EXPOSE 8080
