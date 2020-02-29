git archive -o latest.zip HEAD
if [ $? -ne 0 ]; then
    echo "git archive failed"
    exit 1
fi
response_code=$(
    curl -s -o output.txt -w '%{http_code}' \
    -X POST "https://lanyi.altervista.org/webhook.php?from=travis&repo=BSG-75%2FNSS" \
    --user generic:$UPLOAD_SECRET \
    -H "Content-Type: application/octet-stream" \
    --data-binary "@./latest.zip"
)
echo
cat output.txt
if [[ $response_code -ne 200 ]]; then
    echo "curl upload failed"
    exit 1
fi
exit 0