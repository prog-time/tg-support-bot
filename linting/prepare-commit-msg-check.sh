#!/bin/bash

set -e

bash linting/preparation/prepare-commit-message.sh

bash linting/preparation/add_task_name_in_commit.sh

bash linting/preparation/add_files_in_commit_message.sh
