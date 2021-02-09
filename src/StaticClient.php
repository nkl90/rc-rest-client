<?php

namespace Nkl\RocketChatRestClient;

use Nkl\RocketChatRestClient\Exception\DuplicateChannelNameException;
use Nkl\RocketChatRestClient\Exception\RoomNotFoundException;
use Nkl\RocketChatRestClient\Exception\UnknownErrorException;
use Nkl\RocketChatRestClient\Exception\UserNotFoundException;
use GuzzleHttp\Client;
use GuzzleHttp\Command\Exception\CommandClientException;
use GuzzleHttp\Command\Guzzle\Description;
use GuzzleHttp\Command\Guzzle\GuzzleClient;
use Knp\Component\Pager\Pagination\SlidingPagination;
use Symfony\Component\HttpFoundation\Request;

class StaticClient extends GuzzleClient
{
    public const ADMIN_CREDENTIALS_SRC = 'rc_admin_credentials.json';
    public const E_CLIENT_NOT_AUTHORIZED = 'You must be logged in to do this.';
    public const E_DUPLICATE_CHANNEL_NAME = 'error-duplicate-channel-name';
    public const E_ROOM_NOT_FOUND = 'error-room-not-found';
    public const E_USER_NOT_FOUND = 'User not found.';
    public const E_TOTP_INVALID = 'totp-invalid';
    public const METHOD_USERS_CREATE = 'users.create';
    public const METHOD_USERS_INFO = 'users.info';
    public const METHOD_USERS_UPDATE = 'users.update';
    public const METHOD_LOGIN = 'login';
    public const METHOD_CHANNELS_LIST = 'channels.list';
    public const METHOD_CHANNELS_MESSAGES = 'channels.messages';
    public const METHOD_GROUPS_LIST_ALL = 'groups.listAll';
    public const METHOD_GROUPS_MESSAGES = 'groups.messages';
    public const METHOD_GROUPS_INFO = 'groups.info';
    public const METHOD_GROUPS_MEMBERS = 'groups.members';
    public const METHOD_GROUPS_CREATE = 'groups.create';
    public const METHOD_GROUPS_INVITE = 'groups.invite';
    public const METHOD_GROUPS_KICK = 'groups.kick';
    public const METHOD_IM_LIST_EVERYONE = 'im.list.everyone';
    public const METHOD_IM_MESSAGES = 'im.messages';
    public const METHOD_IM_HISTORY = 'im.history';
    public const METHOD_IM_MESSAGES_OTHERS = 'im.messages.others';
    public const METHOD_CHANNELS_INFO = 'channels.info';

    /***
     * @param array $config
     * @return static
     */
    public static function create($config = [])
    {
        if (!isset($_ENV['RC_SERVER']) || !isset($_ENV['RC_ADMIN_USER']) || !isset($_ENV['RC_ADMIN_PASSWORD'])) {
            throw new \LogicException('RC_SERVER or RC_ADMIN_USER or RC_ADMIN_PASSWORD unidentified ');
        }
        /**
         * Потом переделать это дело на yaml. Все таки json для этого не так удобен (нет комментариев)
         */
        $description = (array) json_decode(
            file_get_contents(__DIR__ . '/Resources/guzzle_service_api_definition.json'),
            true
        );
        $description['baseUrl'] = $_ENV['RC_SERVER'] . '/api/v1/';
        $service_description = new Description($description);

        // Creates the client and sets the default request headers.
        $clientConfig = [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]
        ];

        // кеширование админского токена, т.к. запрос авторизации не чаще чем раз в 30 сек.
        $path = $_ENV['PROJECT_VAR_DIR'] . DIRECTORY_SEPARATOR . self::ADMIN_CREDENTIALS_SRC;
        try {
            if (!file_exists($path)) {
                throw new FileNotFoundException();
            }
            $data = file_get_contents($path);
            if ($data) {
                $adminCredentials = json_decode($data, true);
            } else {
                $adminCredentials = self::getAdminCredentials();
                file_put_contents($path, json_encode($adminCredentials));
            }
        } catch (\LogicException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $adminCredentials = self::getAdminCredentials();
            file_put_contents($path, json_encode($adminCredentials));
        }


        $clientConfig['headers']['X-User-Id'] = $adminCredentials['userId'];
        $clientConfig['headers']['X-Auth-Token'] = $adminCredentials['authToken'];
        $clientConfig['headers']['X-2fa-method'] = 'password';
        $clientConfig['headers']['X-2fa-code'] = hash('sha256', $_ENV['RC_ADMIN_PASSWORD']);

        $client = new Client($clientConfig);

        $commandClient = new static($client, $service_description, null, null, null, $config);
        $testCommand = $commandClient->getCommand(self::METHOD_USERS_INFO, [
            'username' => $_ENV['RC_ADMIN_USER']
        ]);

        try {
            //Тестируем клиента
            $testResult = $commandClient->execute($testCommand)->toArray();
        } catch (CommandClientException $e) {
            if ($e->getResponse()->getStatusCode() == 401) {//скорее всего токен протух
                unlink(self::ADMIN_CREDENTIALS_SRC);//удаляем старый и переинициализируем клиент
            } else {
                // страшно, очень страшно, мы не знаем что это такое,
                // если б мы знали что это такое, мы не знаем что это такое))
                //throw $e;
            }
        } catch (\Exception $e) {
            //dd($e);
        }

        return $commandClient;
    }

    private static function getAdminCredentials()//: array
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $_ENV['RC_SERVER'] . '/api/v1/' . self::METHOD_LOGIN);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'user' => $_ENV['RC_ADMIN_USER'],
            'password' => $_ENV['RC_ADMIN_PASSWORD']
        ]));

        $headers = array();
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new \LogicException(curl_error($ch));
        }
        curl_close($ch);

        $result = json_decode($result, true);

        if(!isset($result['data'])){
            throw new \LogicException('RC Client not Unauthorized. RC_ADMIN_LOGIN or RC_ADMIN_PASSWORD is incorrect');
        }

        return [
            'userId' => $result['data']['userId'],
            'authToken' => $result['data']['authToken']
        ];
    }

    public static function handleException(CommandClientException $e)
    {
        $responseBody = (array) json_decode($e->getResponse()->getBody()->getContents(), true);

        if(isset($responseBody['errorType'])){
            switch ($responseBody['errorType']){
                case self::E_DUPLICATE_CHANNEL_NAME:
                    throw new DuplicateChannelNameException($responseBody['error'], $e->getCommand());
                case self::E_ROOM_NOT_FOUND:
                    throw new RoomNotFoundException($responseBody['error'], $e->getCommand());
            }
        }elseif(isset($responseBody['error'])) {
            switch ($responseBody['error']){
                case self::E_USER_NOT_FOUND:
                    throw new UserNotFoundException($responseBody['error'], $e->getCommand());
                default:
                    throw new UnknownErrorException($responseBody['error'], $e->getCommand());
            }
        }else{
            dump($responseBody);
            dump($e);
            throw new \LogicException('Страшно, очень страшно! Мы не знаем что это такое! Если б знали что это такое - мы не знаем что это такое!');
        }
    }

    public function executeCommandWithPaginationParams(string $name, int $page, int $perPage, array $options = []):array
    {
        $offset = ($page - 1) * $perPage;
        $options['offset'] = $offset;
        $options['count'] = $perPage;
        $client = self::create();
        $command = $client->getCommand($name, $options);
        $response = $client->execute($command)->toArray();
        $items = array_shift($response);
        $totalItems = $response['total'];
        $insertBefore = $offset;
        $insertAfter = $totalItems - $insertBefore - $perPage;

        for($i = 1; $i <= $insertBefore; $i++){
            array_unshift($items, []);
        }
        if($insertAfter > 0) {
            for ($i = 1; $i <= $insertAfter; $i++) {
                array_push($items, []);
            }
        }
        return $items;
    }

}














