<?php


namespace Ostiwe\Handlers;


use Exception;
use Ostiwe\Utils\Wall;
use VK\Client\VKApiClient;
use VK\Exceptions\Api\VKApiMessagesCantFwdException;
use VK\Exceptions\Api\VKApiMessagesChatBotFeatureException;
use VK\Exceptions\Api\VKApiMessagesChatUserNoAccessException;
use VK\Exceptions\Api\VKApiMessagesContactNotFoundException;
use VK\Exceptions\Api\VKApiMessagesDenySendException;
use VK\Exceptions\Api\VKApiMessagesKeyboardInvalidException;
use VK\Exceptions\Api\VKApiMessagesPrivacyException;
use VK\Exceptions\Api\VKApiMessagesTooLongForwardsException;
use VK\Exceptions\Api\VKApiMessagesTooLongMessageException;
use VK\Exceptions\Api\VKApiMessagesTooManyPostsException;
use VK\Exceptions\Api\VKApiMessagesUserBlockedException;
use VK\Exceptions\Api\VKApiWallAddPostException;
use VK\Exceptions\Api\VKApiWallAdsPostLimitReachedException;
use VK\Exceptions\Api\VKApiWallAdsPublishedException;
use VK\Exceptions\Api\VKApiWallLinksForbiddenException;
use VK\Exceptions\Api\VKApiWallTooManyRecipientsException;
use VK\Exceptions\VKApiException;
use VK\Exceptions\VKClientException;

class PubHandler
{
    /**
     * Access user token
     *
     * @var string $userToken
     * */
    public $userToken = null;

    /**
     * User ID
     *
     * @var string|int $userId
     * */
    public $userId = null;

    /**
     * A community token allows working with API on behalf of a group, event or public page
     *
     * @var string $pubToken
     * */
    public $pubToken = null;

    /**
     * Pub ID
     *
     * @var string|int $pubId
     * */
    public $pubId = null;


    /**
     * @var VKApiClient $vk
     * */
    public $vk = null;

    /**
     * @var Wall $utilsWall
     * */
    public $utilsWall = null;

    /**
     * PubHandler constructor.
     *
     * @param string     $userToken Access user token
     * @param string|int $userId    User ID
     * @param string     $pubToken  A community token allows working with API on behalf of a group, event or public page
     * @param string|int $pubId     Group ID
     */
    public function __construct($userToken, $userId, $pubToken, $pubId)
    {
        $this->userToken = $userToken;
        $this->userId = $userId;

        $this->pubToken = $pubToken;
        $this->pubId = $pubId;

        if ($this->vk === null) {
            $this->vk = new VKApiClient();
        }
        if ($this->utilsWall === null) {
            $this->utilsWall = new Wall();
        }
    }

    /**
     * Handler for this community
     *
     * @param array  $attachmentsList Array of images uploaded to the server VK
     * @param array  $pubParams       Parameters for the community. At this time, you need to set the interval
     * @param string $postText        The text of the post
     * @throws Exception
     */
    public function handle(array $attachmentsList, array $pubParams, string $postText = '')
    {
        try {
            $postInfo = $this->post($pubParams, $attachmentsList, $postText);
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e->getPrevious());
        }
        $postDate = date('d.m.Y в H:i', $postInfo['date']);
        $textMessage = "Пост будет опубликован $postDate <br>";
        $textMessage .= "vk.com/wall-{$this->pubId}_{$postInfo['post_id']}";

        try {
            $this->sendNotificationMessage($textMessage);
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e->getPrevious());
        }

        return [
            'pub_id' => $this->pubId,
            'post_id' => $postInfo['post_id'],
            'publish_date' => [
                'unix' => $postInfo['date'],
                'human' => $postDate
            ]
        ];
    }

    /**
     * Gets the time of the last post and puts the post in the queue for publication.
     * If there are no posts in the queue, the post will be published after the specified period.
     *
     * @param array  $pubParams       Parameters for the community. At this time, you need to set the interval
     * @param array  $attachmentsList Array of images uploaded to the server VK
     * @param string $postText        The text that will be attached to the post
     * @return array Array containing the post id and the time of its publication
     * @throws Exception
     */
    public function post(array $pubParams, array $attachmentsList, $postText = '')
    {
        try {
            $lastPostPublishDate = $this->utilsWall->getLastPostTime($this->userToken, $this->pubId);
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e->getPrevious());
        }
        $publishDate = $lastPostPublishDate + ($pubParams['interval'] * 3600);

        try {
            $postId = $this->vk->wall()->post($this->userToken, [
                'owner_id' => -$this->pubId,
                'from_group' => true,
                'message' => $postText,
                'attachments' => implode(',', $attachmentsList),
                'publish_date' => $publishDate
            ]);

        } catch (VKApiWallAddPostException $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e->getPrevious());
        } catch (VKApiWallAdsPostLimitReachedException $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e->getPrevious());
        } catch (VKApiWallAdsPublishedException $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e->getPrevious());
        } catch (VKApiWallLinksForbiddenException $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e->getPrevious());
        } catch (VKApiWallTooManyRecipientsException $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e->getPrevious());
        } catch (VKApiException $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e->getPrevious());
        } catch (VKClientException $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e->getPrevious());
        }
        return [
            'post_id' => $postId['post_id'],
            'date' => $publishDate
        ];
    }

    /**
     * Sends a notification message
     *
     * @param string $message
     * @param array  $attachments Array of images uploaded to the server VK
     * @throws Exception
     */
    public function sendNotificationMessage(string $message, array $attachments = [])
    {
        $randomId = rand(99999, getrandmax());
        try {
            $this->vk->messages()->send($this->pubToken, [
                'user_id' => $this->userId,
                'random_id' => $randomId,
                'message' => $message,
                'attachment' => implode(',', $attachments)
            ]);
        } catch (VKApiMessagesCantFwdException $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e->getPrevious());
        } catch (VKApiMessagesChatBotFeatureException $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e->getPrevious());
        } catch (VKApiMessagesChatUserNoAccessException $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e->getPrevious());
        } catch (VKApiMessagesContactNotFoundException $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e->getPrevious());
        } catch (VKApiMessagesDenySendException $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e->getPrevious());
        } catch (VKApiMessagesKeyboardInvalidException $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e->getPrevious());
        } catch (VKApiMessagesPrivacyException $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e->getPrevious());
        } catch (VKApiMessagesTooLongForwardsException $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e->getPrevious());
        } catch (VKApiMessagesTooLongMessageException $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e->getPrevious());
        } catch (VKApiMessagesTooManyPostsException $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e->getPrevious());
        } catch (VKApiMessagesUserBlockedException $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e->getPrevious());
        } catch (VKApiException $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e->getPrevious());
        } catch (VKClientException $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e->getPrevious());
        }
    }
}