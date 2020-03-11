<?php


namespace Ostiwe\Utils;

use Exception;
use VK\Client\VKApiClient;
use VK\Exceptions\Api\VKApiBlockedException;
use VK\Exceptions\VKApiException;
use VK\Exceptions\VKClientException;

class Wall
{
    /**
     * @var VKApiClient $vk
     * */
    private $vk = null;

    /**
     * Wall constructor.
     */
    public function __construct()
    {
        if ($this->vk === null) {
            $this->vk = new VKApiClient();
        }
    }

    /**
     * @param string $userToken Access user token
     * @param string|int $pubID ID of the community
     * @param int $offset
     * @return int
     * @throws Exception
     */
    public function getLastPostTime($userToken, $pubID, $offset = 0)
    {
        try {
            $postsObj = $this->vk->wall()->get($userToken, [
                'owner_id' => -$pubID,
                'count' => 100,
                'offset' => $offset,
                'filter' => 'postponed',
            ]);
        } catch (VKApiBlockedException $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e->getPrevious());
        } catch (VKApiException $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e->getPrevious());
        } catch (VKClientException $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e->getPrevious());
        }

        if ($postsObj['count'] === 0) {
            return time();
        }
        if ($postsObj['count'] > 99 && $offset === 0) {
            return self::getLastPostTime($userToken, $pubID, $postsObj['count'] - 2);
        }
        $lastPost = array_pop($postsObj['items']);
        return $lastPost['date'];
    }
}