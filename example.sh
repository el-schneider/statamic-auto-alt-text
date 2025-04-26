#! /bin/bash

MOONDREAM_API_KEY=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJrZXlfaWQiOiIwN2U2OTFlZi1mNDc1LTRlODAtYjE5OC03Y2E3MjYzYzI5OGEiLCJvcmdfaWQiOiJvRlpabFk2NWlsdXZTSjVYVjBhYTNYRWRnbXZ3RkxDRyIsImlhdCI6MTc0NTY4OTk4MSwidmVyIjoxfQ.enGFzwyIzrnB8v4lMAARuV8IUVXCYQYOJ1531huLpq4

# Encode the image to base64 without line breaks (use -b 0 for macOS base64)
# Adjust the base64 command if needed for your OS (e.g., base64 -w 0 for GNU)
IMG_BASE64=$(cat ./frieren.jpg | base64 -w 0)

# Check if base64 encoding was successful or file exists
if [ -z "$IMG_BASE64" ] || [ ! -f "./frieren.jpg" ]; then
  echo "Error: Failed to encode image or ./frieren.jpg not found."
  exit 1
fi

# Construct the JSON payload using a heredoc
JSON_PAYLOAD=$(cat <<EOF
{
  "image_url": "data:image/jpeg;base64,${IMG_BASE64}",
  "stream": false
}
EOF
)

# Make the API call
curl -X POST --location "https://api.moondream.ai/v1/caption" \
  --header "X-Moondream-Auth: ${MOONDREAM_API_KEY}" \
  --header "Content-Type: application/json" \
  --data "${JSON_PAYLOAD}"

echo # Add a newline for cleaner output
