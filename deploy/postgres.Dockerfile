FROM postgres:18-alpine@sha256:77f585114c32fbca283dc835b0596f4e52b51b4c6662d7810b2f4084f60a1873
# The stock gosu helper embeds an old Go stdlib. The entrypoint only uses its
# user/exec interface; replace it with Alpine's small C implementation.
RUN apk add --no-cache su-exec \
    && rm /usr/local/bin/gosu \
    && sed -i 's/exec gosu postgres/exec su-exec postgres/' /usr/local/bin/docker-entrypoint.sh \
    && su-exec postgres sh -c 'test "$(id -u)" = "$(id -u postgres)"'
