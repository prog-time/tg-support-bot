#!/bin/bash

set -e

bash scripts/preparation/prepare-commit-message.sh

bash scripts/preparation/add_task_name_in_commit.sh

bash scripts/preparation/add_files_in_commit_message.sh
