git archive -o latest.zip HEAD
if [ $? -ne 0 ]; then
    echo "git archive failed"
    exit 1
fi
response_code=$(
    curl -s -o output.txt -w '%{http_code}' \
    --user generic:$UPLOAD_SECRET "https://lanyi.altervista.org/webhook.php?from=travis&repo=BSG-75%2FNSS" \
    -X POST \
    -H "Content-Type: application/octet-stream" \
    --data-binary "@./latest.zip"
)
cat output.txt
echo
if [[ $response_code -ne 200 ]]; then
    echo "curl upload failed"
    exit 1
fi
exit 0