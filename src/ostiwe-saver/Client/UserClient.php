<?php

namespace Ostiwe\Client;

use Exception;
use VK\Client\VKApiClient;
use Ostiwe\Utils\Photos;

class UserClient
{
    private $userToken = null;
    private $userId = null;

    private $groups = null;

    private $currentPubId = null;

    private $vk = null;
    private $utilsPhoto = null;

    private $handlersPatch = null;

    /**
     * Bot constructor.
     * @param $userId
     * @param $userToken
     * @param array $groups
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

    public function setHandlersPatch(string $path)
    {
        if (!file_exists($path) && !is_dir($path)) {
            mkdir($path);
        }
        $this->handlersPatch = $path;
    }

    /**
     * @param null $groupId
     * @return string
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
     * @param array $callbackObj
     * @throws Exception
     */
    public function callbackHandler($callbackObj = [])
    {
        if (empty($callbackObj)) {
            throw new Exception ('Empty message.');
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
     * @param $messageObj
     * @throws Exception
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
     * @param $eventObj
     * @throws Exception
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
     * @param $className
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