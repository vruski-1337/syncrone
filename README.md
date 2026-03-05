# syncrone

A simple Node.js web server built with Express.

## Getting Started

### Install dependencies

```bash
npm install
```

### Run the server

```bash
npm start
```

The server listens on port `3000` by default. Set the `PORT` environment variable to use a different port.

## Endpoints

| Method | Path      | Description               |
|--------|-----------|---------------------------|
| GET    | `/`       | Returns a welcome message |
| GET    | `/health` | Health check              |
