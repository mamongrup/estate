FROM ghcr.io/gleam-lang/gleam:v1.18.1-erlang-alpine AS build
WORKDIR /app
COPY gleam.toml ./
RUN gleam deps download
COPY src ./src
RUN gleam export erlang-shipment

FROM erlang:28-alpine
WORKDIR /app
COPY --from=build /app/build/erlang-shipment ./
COPY index.html admin.html ilan.html robots.txt ./public/
COPY assets ./public/assets
EXPOSE 8080
CMD ["./entrypoint.sh", "run"]
