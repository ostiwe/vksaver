<?php


namespace Ostiwe\Utils;

use Exception;
use VK\Client\VKApiClient;
use VK\Exceptions\Api\VKApiBlockedException;
use VK\Exceptions\VKApiException;
use VK\Exceptions\VKClientException;

class Wall
{
    private $vk = null;

    public function __construct()
    {
        if ($this->vk === null) {
            $this->vk = new VKApiClient();
        }
    }

    public function getLastPostTime($userToken, $pubID)
    {
        try {
            $postsObj = $this->vk->wall()->get($userToken, [
                'owner_id' => -$pubID,
                'count' => 100,
                'filter' => 'postponed',
            ]);
        } catch (VKApiBlockedException $e) {
            throw new Exception($e->getMessage(), null, $e);
        } catch (VKApiException $e) {
            throw new Exception($e->getMessage(), null, $e);
        } catch (VKClientException $e) {
            throw new Exception($e->getMessage(), null, $e);
        }

        if ($postsObj['count'] === 0) {
            return time();
        }
        $lastPost = array_pop($postsObj['items']);
        return $lastPost['date'];
    }
}