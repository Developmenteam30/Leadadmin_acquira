#!/bin/bash
export TZ=":US/Eastern"
if [ "$(date +%z)" == "$1" ]; then
  shift
  exec $@
fi
