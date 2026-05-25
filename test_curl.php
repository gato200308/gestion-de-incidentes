<?php
$context = stream_context_create(['http' => ['ignore_errors' => true]]);
$content = file_get_contents('https://orangered-otter-125144.hostingersite.com/auth/check_session.php', false, $context);
file_put_contents('test_curl_output.txt', $content);
echo "Done";

