<?php

/**
 * ssh-keygen -t rsa -b 2048 -C "example@email.com"
 * cat ~/.ssh/id_rsa.pub copy token and add to gitlab
 * ssh-add ~/.ssh/id_rsa
 * The Options.
 * Only `secret` and `directory` are required.
 * @var array
 */
$options = [
    'secret' => '', // @link https://example.com/deploy.php?secret=WEBHOOKSECRET
    'directory' => __DIR__,
    'log' => 'system/storage/logs/deployments.log',
    'branch' => 'master',
    'remote' => 'origin',
    'date_format' => 'Y-m-d H:i:sP',
    'slack_user' => '',
    'slack_webhook_url' => '',
    'slack_channel' => '',
    'telegram_token' => '',
    'telegram_group_id' => '',
    'is_adaptive' => '',
];

$deploy = new Deploy($options);
$deploy->validateSignature();
$deploy->execute();


/**
 * Class Deploy
 */
class Deploy
{
    /**
     * The name of the file that will be used for logging deployments. Set to
     * FALSE to disable logging.
     *
     * @var string
     */
    private string $_log = 'deployments.log';
    /**
     * The timestamp format used for logging.
     *
     * @link http://www.php.net/manual/en/function.date.php
     * @var string
     */
    private string $_date_format = 'Y-m-d H:i:sP';
    /**
     * The name of the branch to pull from.
     *
     * @var string
     */
    private string $_branch = 'master';
    private string $_current_branch = '';
    private string $_commit = '';
    /**
     * The name of the remote to pull from.
     *
     * @var string
     */
    private string $_remote = 'origin';


    /**
     * The directory where your website and git repository are located, can be
     * a relative or absolute path
     *
     * @var string
     */
    private string $_directory;
    private string $_secret;
    private string $_slack_webhook_url = '';
    private string $_slack_channel = '';
    private string $_slack_user = '';
    private string $_telegram_token = '';
    private string $_telegram_group_id = '';

    /**
     * Deploy constructor.
     * @param array $options
     * @throws Exception
     */
    public function __construct(array $options = [])
    {
        $available_options = [
            'secret',
            'directory',
            'log',
            'date_format',
            'branch',
            'remote',
            'slack_webhook_url',
            'slack_channel',
            'slack_user',
            'telegram_token',
            'telegram_group_id',
        ];

        foreach ($options as $option => $value) {
            if (in_array($option, $available_options, true)) {
                $this->{'_' . $option} = $value;
            }
        }

        $this->log('Attempting deployment…');
        $this->log('Work Directory: ' . $this->_directory);
        if (empty($this->_directory)) {
            $this->doException('Directory is required.');
        }
        $this->_branch = $_GET['branch'] ?? $this->_branch;
        $this->_current_branch = trim(shell_exec('git rev-parse --abbrev-ref HEAD'));

        if (!$this->_current_branch || $this->_current_branch === 'master') {
            $this->doException('Current branch "' . $this->_current_branch . '" is wrong');
        }

        $this->_commit = trim(shell_exec('git rev-parse HEAD'));

        // Set a default header for every response
        header('Content-type: application/json');
    }

    /**
     * Writes a message to the log file.
     *
     * @param array|string $message The message to write
     * @param string $type The type of log message (e.g. INFO, DEBUG, ERROR, etc.)
     */
    public function log($message, string $type = 'INFO'): void
    {
        if ($this->_log) {
            $filename = $this->_log;
            if (!file_exists($filename)) {
                file_put_contents($filename, '');
                chmod($filename, 666);
            }
            file_put_contents($filename, date($this->_date_format) . ' --- ' . $type . ': ' . print_r($message, true) . PHP_EOL, FILE_APPEND);
        }
    }

    /**
     * @param $message
     * @param string $channel
     * @param string $username
     * @return bool|string
     */
    public function slack($message, string $channel = '', string $username = '')
    {
        $channel = $channel ?: $this->_slack_channel;
        $username = $username ?: $this->_slack_user;
        $message = print_r($message, true);
        $result = '';

        if ($this->_slack_webhook_url && $channel && $message) {
            $message = urlencode($message);
            $data = 'payload=' . json_encode(array(
                'text' => $message,
                'room' => '#' . $channel,
                'username' => $username,
                'icon_emoji' => ':ghost:',
            ));

            $url = $this->_slack_webhook_url;

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $result = curl_exec($ch);
            if ($result === false) {
                $result = 'Curl error: ' . curl_error($ch);
            }

            curl_close($ch);
        }

        return $result;
    }

    /**
     * @param string|array $message
     */
    private function telegram($message): void
    {
        $botToken = $this->_telegram_token;
        $chatIds = (array)$this->_telegram_group_id;

        if (!$botToken || !$chatIds || !$message) {
            return;
        }

        $website = "https://api.telegram.org/bot" . $botToken;

        $message = str_replace("\t", ' ', $message); // convert tabs to spaces
        $message = preg_replace('/\s+/', ' ', $message); // compress multispaces
        $message = preg_replace('/\s*<br>\s*/', "\r\n", $message); // convert <br> to nl
        $message = str_replace('<hr>', "\r\n" . str_repeat('-', 65) . "\r\n", $message); // <hr>
        $message = trim(strip_tags($message));  // remove tags

        $messageArray = explode(PHP_EOL, $message);

        $outputArr = [];
        $message = '';
        foreach ($messageArray as $chunk) {
            if ((strlen($chunk) + strlen($message)) < 4096) {
                $message .= $message ? PHP_EOL . $chunk : $chunk;
            } else {
                $output[] = $message;
                $message = $chunk;
            }
        }

        if ($message) {
            $outputArr[] = $message;
        }
        $ch = '';
        foreach ($outputArr as $item) {
            $item = strlen($item) > 4090 ? substr($item, 0, 4090) . ' ....' : $item;
            $ch = curl_init($website . '/sendMessage');
            foreach ($chatIds as $chatId) {
                $params = [
                    'chat_id' => $chatId,
                    'text' => $item,
                ];

                curl_setopt($ch, CURLOPT_HEADER, false);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, ($params));
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_exec($ch);
            }
        }

        if ($ch) {
            curl_close($ch);
        }
    }

    /**
     * Executes the necessary commands to deploy the website.
     */
    public function execute(): void
    {
        try {

            if (!$this->_branch || !$this->_current_branch || $this->_branch !== $this->_current_branch) {
                return;
            }

            // Make sure we're in the right directory
            $commands = [
                //'Making backup... ' => 'cp -r '. $this->_directory . ' old_' . $this->_directory . '_' . date('Y_m_d'),
                'Changing working directory... ' => 'cd ' . $this->_directory,
                'Git status before pulling... ' => 'git status',
                'Start update shell script' => './update.sh ',
                'Git status after update... ' => 'git status',
                'Remove system cache...' => 'php oc_cli.php site tool/clean_cache',
                'Remove html cache...' => 'rm -rf system/storage/htmlcache'
            ];

            $this->log('');
            $this->log('=============================================================================');

            foreach ($commands as $description => $command) {
                $this->log('---------------' . $description . ' : ' . $command . '---------------');
                $output = shell_exec($command);
                $this->log($output);
            }

            $successMessage = 'Deployment successful.';
            $this->log($successMessage);
            $this->sendMessage($successMessage);
            $this->log('-------------------------------------------------------------');

            echo json_encode(['status' => 'ok', JSON_PRETTY_PRINT]);
        } catch (Exception $e) {
            if ($this->_commit) {
                shell_exec('git reset --hard ' . $this->_commit);
            }

            $this->log($e, 'ERROR');
            $this->sendMessage($e->getMessage());
            http_response_code(500);
            exit(json_encode(['error' => 'Exception in deploy script line ' . $e->getMessage()], JSON_PRETTY_PRINT));
        }
    }

    private function sendMessage(string $message): void
    {
        $this->slack($message);
        $this->telegram($message);
    }

    private function doException($message): void
    {
        $this->log($message, 'ERROR');
        $this->sendMessage($message);
        http_response_code(401);
        exit('Houston, we’ve had a problem');
    }

    /**
     * Parse delivered hashed or plain signature and bail if verification fails.
     */
    public function validateSignature(): void
    {
        // GitHub and Gitea forward a hashed secret
        $hashed_signature = $_SERVER['HTTP_X_HUB_SIGNATURE'] ?? $_SERVER['HTTP_X_GITEA_SIGNATURE'] ?? null;
        $hash = $algo = '';
        // GitLab just sends the plain token
        $generic_signature = $_SERVER['HTTP_X_GITLAB_TOKEN'] ?? $_GET['secret'] ?? null;

        if (empty($hashed_signature) && empty($generic_signature)) {
            $this->doException('No secret given.');
        }

        // Check content type of POST requests
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SERVER['CONTENT_TYPE'] !== 'application/json') {
            $this->doException('Content type does\'t match `application/json`.');
        }

        if (!empty($hashed_signature)) {
            // GitHub prepends the applied hashing algorithm (`sha1`) in its signature
            // Gitea doesn't hint its hashing algorithm, but implies `sha256`
            if (isset($_SERVER['HTTP_X_HUB_SIGNATURE'])) {
                // Split signature into algorithm and hash
                [$algo, $hash] = explode('=', $hashed_signature, 2);
            } elseif (isset($_SERVER['HTTP_X_GITEA_SIGNATURE'])) {
                $algo = 'sha256';
                $hash = $hashed_signature;
            }

            // Get payload
            $payload = file_get_contents('php://input');

            // Calculate hash based on payload and the secret
            $payload_hash = hash_hmac($algo, $payload, $this->_secret);

            // Check if hashes are equivalent
            if (!hash_equals($hash, $payload_hash)) {
                $this->doException('Hook secret does\'t match.');
            }
        } elseif (!empty($generic_signature) && $generic_signature !== $this->_secret) {
            $this->doException('Hook secret does\'t match.');
        }
    }
}
