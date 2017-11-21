<?php
/**
 * GitHub Post to TeamworkPM
 * @version 1.2
 */

require_once 'config.php';

$prefix = preg_quote(_get( 'prefix', '#' ));
$comment_regex = '/' . $prefix . '([A-Za-z0-9_]+)/';

$github_headers = @apache_request_headers();
$github_event = @$github_headers[ 'X-GitHub-Event' ];
if('ping' === $github_event) {
    echo 'Ping. URL: ' . COMMENT_URL;
}

try {
    // Convert the post data
    $data = isset($_POST[ 'payload' ]) ? /*stripslashes(*/$_POST[ 'payload' ] : null;
    $data = str_replace([ '\n', '\r' ], ' ', $data );
    $postdata = json_decode($data);

    if ($data && $postdata) {
        $repo_name  = $postdata->repository->full_name;

        // Iterate through each commit to see if we have a related task
        foreach ($postdata->commits as $commit) {
            // Format message data
            $commitID   = $commit->id;
            $comment    = $commit->message;
            $url        = $commit->url;
            $timestamp  = $commit->timestamp;
            $author     = $commit->author->name;

            // Get any commit messages that have a # tag (points to a resource ID in Teamwork)
            preg_match_all( $comment_regex, $comment, $matches );
            // Remove the first index since it's the original
            $resourceID = array_pop($matches);

            // Format the message that will post to Teamwork
            $body = strtr(COMMENT_TEMPLATE, array(
                        '{COMMENT}' => $comment,
                        '{URL}'     => $url,
                        '{COMMIT_NAME}' => $repo_name .'@'. substr($commitID, 0, 7),
                        '{AUTHOR}'  => $author,
                        '{DATE}'    => date(DATE_FORMAT, strtotime($timestamp)) ));
            // https://developer.teamwork.com/comments#creating_a_commen
            $params = array(
                'comment' => array(
                    'body' => $body,
                    # 'notify' => TEAMWORK_NOTIFY,
                    # 'isprivate' => false,
                )
            );
            $postData = json_encode($params);
            _debug($params);

            if(count($resourceID) > 0) {
                // Iterate through each hash tag / resource and make a request
                foreach ($resourceID as $resource) {
                    if($resource < MIN_RESOURCE_ID) {
                        echo "Task #$resource: skipping" . PHP_EOL;
                        continue;
                    }

                    // Create the comment
                    $c = curl_init();
                    $headers = array(
                        'Authorization: BASIC '. base64_encode(USER_TOKEN . ':xxxzzz'),
                        'Content-Type: application/json',
                        'Accept: application/json'
                    );
                    curl_setopt_array($c, array(
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_HEADER         => true,
                        CURLOPT_SSL_VERIFYHOST => false,
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_POST => true,
                        CURLOPT_PROXY => HTTP_PROXY,
                        CURLOPT_HTTPHEADER => $headers
                    ));
                    $teamwork_url = sprintf( COMMENT_URL, $resource );
                    curl_setopt($c, CURLOPT_URL, $teamwork_url);
                    curl_setopt($c, CURLOPT_POSTFIELDS, $postData );

                    $response = curl_exec($c);
                    $httpCode = curl_getinfo($c, CURLINFO_HTTP_CODE);
                    curl_close($c);

                    echo "Task #$resource ($httpCode): " . $teamwork_url . PHP_EOL;
                    _debug($response);
                }
            }
        }
    }

    if (!$data) {
        throw new Exception( 'Warning: no {payload}' );
    }

} catch (Exception $e) {
    _debug( $e );
    echo $e->getMessage();
}


function _get( $key, $default = NULL ) {
    return isset($_GET[ $key ]) ? $_GET[ $key ] : $default;
}

function _debug($obj) {
    if (_get( 'debug' )) {
        print_r($obj);
    }
}
