# notmore.

A small PHP app that wraps the [notmuch](https://notmuchmail.org/) CLI to search and browse mail. Inspired by the mostly abandoned [netviel](https://github.com/DavidMStraub/netviel).

## Docker Setup

The container expects a maildir mounted at `/mail`. The notmuch database will be created at `/notmuch`. You can customize the notmuch config by providing environment variables.

```bash
docker run --rm \
  -p 8000:8000 \
  -e NOTMUCH_NAME="Your Name" \
  -e NOTMUCH_PRIMARY_EMAIL="you@example.com" \
  -e NOTMUCH_OTHER_EMAILS="alias@example.com;another@example.com" \
  -v "$(pwd)/data/mail:/mail:ro" \
  -v "$(pwd)/data/notmuch:/notmuch" \
  ghcr.io/splitbrain/notmore:latest
```

On first start the entrypoint writes the notmuch config, initializes the database with `notmuch new`, and then launches the built-in PHP server on port 8000.

You probably want to periodically run `notmuch new` to update the database with new mail. You can do this by execing into the container or setting up a separate cronjob/container. 

```yaml
services:
  notmore:
    image: ghcr.io/splitbrain/notmore:latest
    ports:
      - "8000:8000"
    environment:
      NOTMUCH_NAME: "Your Name"
      NOTMUCH_PRIMARY_EMAIL: "you@example.com"
      NOTMUCH_OTHER_EMAILS: "alias@example.com;another@example.com" # optional
    volumes:
      - ./data/mail:/mail:ro       # point at your maildir
      - ./data/notmuch:/notmuch    # notmuch database path
```

Bring the container up with:

```bash
docker compose up --build
```

## License

Copyright 2026 Andreas Gohr

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the “Software”), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

