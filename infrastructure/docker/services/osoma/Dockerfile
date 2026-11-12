#syntax=docker/dockerfile:1.4

# Versions
FROM node:22-alpine AS node_upstream

# Base stage for dev
FROM node_upstream AS base

# hadolint ignore=DL3018
RUN apk add --no-cache libc6-compat bash

WORKDIR /srv/app

RUN corepack enable && \
	corepack prepare --activate pnpm@10.28.2 && \
	pnpm config -g set store-dir /.pnpm-store

# Development image
FROM base AS dev

EXPOSE 5173

# Vite needs to listen on 0.0.0.0 to be accessible from Docker host
ENV HOST=0.0.0.0

CMD ["sh", "-c", "pnpm install --frozen-lockfile && pnpm run dev --host 0.0.0.0"]

# Preview image for a more stable runtime behind Traefik/E2E
FROM base AS preview

EXPOSE 4173

ENV HOST=0.0.0.0

CMD ["sh", "-c", "pnpm install --frozen-lockfile && pnpm run build && pnpm run preview --host 0.0.0.0 --port 4173"]
