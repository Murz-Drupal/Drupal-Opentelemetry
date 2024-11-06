#!/bin/bash

# A command to wait for a port to be open.

HOST=localhost
PORT=$1

printf "Waiting for port $1 to be open."
for _ in `seq 1 20`; do
    printf .
    if `printf "" 2>>/dev/null >>/dev/tcp/$HOST/$PORT`; then
        printf "\nPort $1 is open."
        exit 0
    fi
    sleep 0.5
done

printf "\nError: port $1 is not open, exiting by timeout.\n"
exit 1
