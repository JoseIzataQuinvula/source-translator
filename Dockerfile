FROM rust:1.85-slim AS builder
WORKDIR /app
COPY . .
RUN apt-get update && apt-get install -y pkg-config libssl-dev && rm -rf /var/lib/apt/lists/*
RUN cargo build --release --bin source-translator-server

FROM debian:bookworm-slim
RUN useradd -r -s /bin/false nonroot
WORKDIR /app
COPY --from=builder /app/target/release/source-translator-server .
COPY --from=builder /app/sdk/php/locales ./locales/
RUN chown -R nonroot:nonroot /app
USER nonroot
EXPOSE 3000
CMD ["./source-translator-server"]
