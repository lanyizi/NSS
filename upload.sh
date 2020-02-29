git archive -o latest.zip HEAD &&
curl -X POST "https://lanyi.altervista.org/webhook.php?from=travis&repo=BSG-75%2FNSS" \
    --user generic:$UPLOAD_SECRET \
    -H "Content-Type: application/octet-stream" \
    --data-binary "@./latest.zip"