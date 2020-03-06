<?php

namespace Ostiwe\Client;

use Exception;
use VK\Client\VKApiClient;
use Ostiwe\Utils\Photos;

class UserClient
{
    /**
     * Access user token
     * @var string $userToken
     */
    private $userToken = null;

    /**
     * User ID
     * @var string|int $userId
     * */
    private $userId = null;

    /**
     * Array of arrays whose keys are group IDs
     * @var array $groups
     * */
    private $groups = null;

    /**
     * ID of the community
     * @var string|int $currentPubId
     * */
    private $currentPubId = null;

    /**
     * @var VKApiClient $vk
     * */
    private $vk = null;

    /**
     * @var Photos $utilsPhoto
     * */
    private $utilsPhoto = null;

    /**
     * Patch to custom handlers for communities
     * @var string $handlersPatch
     * */
    private $handlersPatch = null;

    /**
     * Handler constructor.
     *
     * @param string|int $userId User ID
     * @param string $userToken Access user token
     * @param array $groups Array of arrays whose keys are group IDs
     * - @var int post_interval: The spacing between the posts, specified in hours
     * - @var bool liked_only: Process only with likes (if there is more than one image in the post)
     * - @var string confirmation_code: The string that the server should return when confirming
     * - @var string secret: An arbitrary string up to 50 symbols may contain numbers and Latin letters
     * - @var string access_token: A community token allows working with API on behalf of a group, event or public page
     *
     */
    public function __construct($userId, $userToken, array $groups)
    {
        if ($this->vk === null) {
            $this->vk = new VKApiClient();
        }
        if ($this->utilsPhoto === null) {
            $this->utilsPhoto = new Photos($userId);
        }

        $this->userId = $userId;
        $this->userToken = $userToken;
        $this->groups = $groups;
    }

    /**
     * Sets the path to custom handler classes
     *
     * @param string $path Absolute path to the folder with custom handlers
     */
    public function setHandlersPatch(string $path)
    {
        if (!file_exists($path) && !is_dir($path)) {
            mkdir($path);
        }
        $this->handlersPatch = $path;
    }

    /**
     * Returns a confirmation code for the current community
     *
     * @param null $groupId Group ID
     * @return string Returns a confirmation code for the current community
     * @throws Exception
     */
    public function getConfirmationCode($groupId = null): string
    {
        if (!isset($this->groups[$groupId]['confirmation_code'])) {
            throw new Exception("Secret key for current club id ($groupId) not found!", 31);
        }
        return $this->groups[$groupId]['confirmation_code'];
    }

    /**
     * Method for handling all types of notifications
     *
     * @param array $callbackObj The decoded notification
     * @throws Exception
     * @see https://vk.com/dev/callback_api?f=2.%20%D0%A4%D0%BE%D1%80%D0%BC%D0%B0%D1%82%20%D0%B4%D0%B0%D0%BD%D0%BD%D1%8B%D1%85
     */
    public function callbackHandler($callbackObj = [])
    {
        if (empty($callbackObj)) {
            throw new Exception ('Empty message.');
        }

        if ($callbackObj['type'] !== 'browser_plugin' && $callbackObj['object']['message']['from_id'] != $this->userId) {
            echo 'ok';
            return;
        }

        if (!isset($callbackObj['group_id']) || empty($callbackObj['group_id'])) {
            throw new Exception ('Param "group_id" is required.');
        }
        if (!isset($callbackObj['secret']) || empty($callbackObj['secret'])) {
            throw new Exception ('Param "secret" is required.');
        }

        $groupId = $callbackObj['group_id'];
        $this->currentPubId = $groupId;
        if (($this->groups[$groupId]['secret'] !== $callbackObj['secret']) && $callbackObj['type'] !== 'browser_plugin') {
            throw new Exception ('Wrong secret key or current club not registered.');
        }

        if ($callbackObj['type'] === 'browser_plugin' && $this->groups['plugin']['secret'] !== $callbackObj['secret']) {
            throw new Exception ('Wrong plugin secret key or current plugin not registered.');
        }


        switch ($callbackObj['type']) {
            case 'confirmation':
                echo $this->getConfirmationCode($this->currentPubId);
                break;
            case 'message_new':
                $this->closeConnection();
                $this->newMessageHandler($callbackObj['object']['message']);
                break;
            case 'browser_plugin':
                $this->browserPluginHandler($callbackObj);
                break;
            default:
                throw new Exception('Unhandled event type.');
                break;
        }
    }

    /**
     * @param array $messageObj Message object
     * @throws Exception
     * @see https://vk.com/dev/objects/message
     */
    private function newMessageHandler($messageObj)
    {
        if (empty($messageObj)) {
            throw new Exception('Empty message object');
        }
        $groupId = $this->currentPubId;
        $postText = $messageObj['text'];

        $likedOnly = $this->groups[$groupId]['liked_only'];

        $pubHandler = $this->loadMessageHandlerClasses($this->groups[$groupId]['name'] . 'Handler');

        try {
            $attachments = $this->utilsPhoto->checkAttachments($this->userToken, $messageObj['attachments'], $likedOnly);
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), null, $e);
        }

        try {
            $attachmentsPathList = $this->utilsPhoto->downloadPhotoFromVk($this->userToken, $attachments);
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), null, $e);
        }
        try {
            $attachmentsList = $this->utilsPhoto->uploadWallPhotos($this->userToken, $groupId, $attachmentsPathList);
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), null, $e);
        }
        $pubHandler->handle($attachmentsList, [
            'interval' => $this->groups[$groupId]['post_interval']
        ], $postText);
    }

    /**
     * @param array $filesList
     * @param $groupID
     * @return array
     * @throws Exception
     */
    public function uploadLocalFiles(array $filesList, $groupID)
    {
        try {
            return $this->utilsPhoto->uploadWallPhotos($this->userToken, $groupID, $filesList);
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e->getMessage());
        }
    }

    /**
     * Browser extension handler.
     *
     * @param array $eventObj
     * @throws Exception
     * @see https://github.com/ostiwe/vksaver-chrome
     */
    private function browserPluginHandler($eventObj)
    {
        try {
            $downloadedPhoto = $this->utilsPhoto->downloadPhotoFromUri($eventObj['photo']);
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), null, $e);
        }
        try {
            $pubHandler = $this->loadMessageHandlerClasses($this->groups[$eventObj['group_id']]['name'] . 'Handler');
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), null, $e);
        }

        if (empty($downloadedPhoto)) {
            $pubHandler->sendNotificationMessage("Не удалось скачать выбранное вами изображение.<br>{$eventObj['photo']}");
            echo 'ok';
            return;
        }

        try {
            $uploadedPhoto = $this->utilsPhoto->uploadWallPhotos($this->userToken, $eventObj['group_id'], $downloadedPhoto);
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), null, $e);
        }

        $pubHandler->handle($uploadedPhoto, [
            'interval' => $this->groups[$eventObj['group_id']]['post_interval']
        ]);
    }

    /**
     * Loads custom handlers for communities
     *
     * Returns an object of the handler class
     *
     * @param string $className
     * @return mixed
     * @throws Exception
     */
    private function loadMessageHandlerClasses($className)
    {
        if (!class_exists("$className")) {
            if (file_exists($this->handlersPatch . "/{$className}.php")) {
                include_once $this->handlersPatch . "/{$className}.php";
            } else {
                throw new Exception('Pub handler not found.');
            }
        }

        $class = $className;
        $pubToken = $this->groups[$this->currentPubId]['access_token'];

        return new $class($this->userToken, $this->userId, $pubToken, $this->currentPubId);
    }

    /**
     * Displays "OK" (or other message) and closes the connection. Required to prevent sending repeated notifications.
     * (from VK)
     *
     * @param string $text
     */
    public function closeConnection($text = 'ok')
    {
        ob_start();
        echo $text;
        $size = ob_get_length();
        header("Content-Length: $size");
        header('Connection: close');
        ob_end_flush();
        ob_flush();
        flush();
    }
}