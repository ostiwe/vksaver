<?php

namespace Ostiwe\Utils;

use Exception;
use Ostiwe\Handlers\PubHandler;
use VK\Client\VKApiClient;
use VK\Exceptions\Api\VKApiParamAlbumIdException;
use VK\Exceptions\Api\VKApiParamHashException;
use VK\Exceptions\Api\VKApiParamServerException;
use VK\Exceptions\VKApiException;
use VK\Exceptions\VKClientException;

/**
 * Class Photos
 * @package Ostiwe\Utils
 */
class Photos
{
    /**
     * @var VKApiClient|null
     */
    private $vk = null;
    private $userId = null;

    /**
     * Photos utils constructor.
     * @param $userId
     */
    public function __construct($userId)
    {
        if ($this->vk === null) {
            $this->vk = new VKApiClient();
        }
        if ($this->userId === null) {
            $this->userId = $userId;
        }
    }

    /**
     * Downloads images from VK and places them in a temporary folder
     *
     * @param string $userToken Access user token
     * @param array $photoIds An array consisting of identifiers that represent the IDs of the users who posted the photos,
     * and the IDs of the photos themselves, going through the underscore (example: [1_129207899,6492_135055734] )
     * @return array Array containing paths to images
     * @throws Exception
     */
    public function downloadPhotoFromVk(string $userToken, array $photoIds)
    {
        if (!file_exists(__DIR__ . '/tmp') && !is_dir(__DIR__ . '/tmp')) {
            mkdir(__DIR__ . '/tmp');
        }

        $localPatchList = [];
        try {
            $vkPhotos = $this->vk->photos()->getById($userToken, [
                'photos' => implode(',', $photoIds)
            ]);

            foreach ($vkPhotos as $photo) {
                $randName = __DIR__ . '/tmp/image' . time() . random_int(99, 999999999) . '.jpg';
                $localPatchList[] = $randName;
                $BigSizeImage = $photo['sizes'][count($photo['sizes']) - 1]['url'];
                file_put_contents($randName, file_get_contents($BigSizeImage));
                usleep(5000);
            }

        } catch (VKApiException $e) {
            throw new Exception($e->getMessage(), null, $e);
        } catch (VKClientException $e) {
            throw new Exception($e->getMessage(), null, $e);
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), null, $e);
        }

        return $localPatchList;
    }

    /**
     * Downloads images from the link (for extension)
     *
     * @param string $photoUri
     * @return array Array containing paths to images
     * @throws Exception
     */
    public function downloadPhotoFromUri(string $photoUri)
    {
        if (!file_exists(__DIR__ . '/tmp') && !is_dir(__DIR__ . '/tmp')) {
            mkdir(__DIR__ . '/tmp');
        }
        try {
            $randName = __DIR__ . '/tmp/image' . time() . random_int(99, 999999999) . '.jpg';
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), null, $e);
        }
        $localPatchList[] = $randName;
        file_put_contents($randName, file_get_contents($photoUri));

        if (filesize($randName) < 5) {
            unset($localPatchList[$randName]);
        }

        return $localPatchList;
    }

    /**
     * Retrieves the attachment IDs from message object for further processing
     *
     * @param array $attachments Array of attachments from the message object
     * @return array
     */
    private function getAttachmentsObjForExecuteMethod(array $attachments)
    {
        $attachmentsList = [];

        foreach ($attachments as $attachment) {
            $attachmentType = $attachment['type'];

            if ($attachmentType === 'photo') {
                $attachmentsList[] = [
                    $attachmentType,
                    $attachment[$attachmentType]['owner_id'],
                    $attachment[$attachmentType]['id'],
                    $attachment[$attachmentType]['access_key'],
                ];
            }

            if ($attachmentType === 'wall') {
                $wallAttachments = self::getAttachmentsObjForExecuteMethod($attachment[$attachmentType]['attachments']);
                $attachmentsList = array_merge($attachmentsList, $wallAttachments);
            }

        }
        return $attachmentsList;
    }

    /**
     * @param string $userToken Access user token
     * @param array $attachmentsObj The array that was generated in the {@see getAttachmentsObjForExecuteMethod} method
     * @param bool $likedOnly Process only with likes (if there is more than one image in the post)
     * @return array Sorted array of attachments. (Depending on the likedOnly parameter)
     * @throws Exception
     */
    public function checkAttachments(string $userToken, array $attachmentsObj, bool $likedOnly)
    {
        if (empty($attachmentsObj)) {
            return [];
        }
        $attachmentsList = $this->getAttachmentsObjForExecuteMethod($attachmentsObj);
        $jsonAttachmentsList = json_encode($attachmentsList);

        if ($likedOnly) {
            try {
                $executeResponse = $this->vk->getRequest()->post('execute', $userToken, [
                    'code' => "var postPhotos = $jsonAttachmentsList;
                           var likedList = [];
                           var counter = 0;
                           
                           while (counter < postPhotos.length) {
                               var photo = postPhotos[counter][0] + postPhotos[counter][1] + \"_\" + postPhotos[counter][2];
                               var like = API.likes.isLiked({
                                   \"user_id\": {$this->userId},
                                   \"type\": postPhotos[counter][0],
                                   \"owner_id\": postPhotos[counter][1],
                                   \"item_id\": postPhotos[counter][2],
                               }).liked;
                               if (parseInt(like) == 1) {
                                   likedList.push(photo);
                               }
                               counter = counter + 1;
                           }
                           return likedList;",
                ]);

                if (!empty($executeResponse)) {
                    $tmpAttachmentsList = [];
                    foreach ($executeResponse as $attachmentItem) {
                        $tmpAttachmentsList[] = str_replace('photo', '', $attachmentItem);
                    }
                    $attachmentsList = $tmpAttachmentsList;
                    unset($tmpAttachmentsList);
                } else {
                    $tmpAttachmentsList = [];
                    foreach ($attachmentsList as $attachmentItem) {
                        $tmpAttachmentsList[] = "{$attachmentItem[1]}_{$attachmentItem[2]}_{$attachmentItem[3]}";;
                    }
                    $attachmentsList = $tmpAttachmentsList;
                    unset($tmpAttachmentsList);
                }

            } catch (VKApiException $e) {
                throw new Exception($e->getMessage(), null, $e);
            } catch (VKClientException $e) {
                throw new Exception($e->getMessage(), null, $e);
            }

        } else {
            $tmpAttachmentsList = [];
            foreach ($attachmentsList as $attachmentItem) {
                $tmpAttachmentsList[] = "{$attachmentItem[1]}_{$attachmentItem[2]}_{$attachmentItem[3]}";
            }
            $attachmentsList = $tmpAttachmentsList;
            unset($tmpAttachmentsList);
        }
        return $attachmentsList;
    }

    /**
     * Uploads images to the VK server for use in the {@see PubHandler::post} method
     *
     * @param string $userToken Access user token
     * @param string|int $groupId ID of the community where you want to upload a photo to the wall (without the minus sign»)
     * @param array $photoPatchList
     * @return array Array of image IDs uploaded to the VK server (example: [-20629724_271945303,-20629724_271945301] )
     * @throws Exception
     */
    public function uploadWallPhotos(string $userToken, $groupId, array $photoPatchList)
    {
        $uploadedPhoto = [];
        foreach ($photoPatchList as $photo) {
            try {
                $wallPhotoServer = $this->vk->photos()->getWallUploadServer($userToken, [
                    'group_id' => $groupId
                ]);
            } catch (VKApiException $e) {
                throw new Exception($e->getMessage(), null, $e);
            } catch (VKClientException $e) {
                throw new Exception($e->getMessage(), null, $e);
            }
            try {
                $uploadedPhotoInfo = $this->vk->getRequest()->upload($wallPhotoServer['upload_url'], 'photo', $photo);
            } catch (VKApiException $e) {
                throw new Exception($e->getMessage(), null, $e);
            } catch (VKClientException $e) {
                throw new Exception($e->getMessage(), null, $e);
            }
            try {
                $savedPhotoInfo = $this->vk->photos()->saveWallPhoto($userToken, [
                    'group_id' => $groupId,
                    'photo' => $uploadedPhotoInfo['photo'],
                    'server' => $uploadedPhotoInfo['server'],
                    'hash' => $uploadedPhotoInfo['hash'],
                ]);
                $uploadedPhoto[] = "photo{$savedPhotoInfo[0]['owner_id']}_{$savedPhotoInfo[0]['id']}_{$savedPhotoInfo[0]['access_key']}";
                unlink($photo);
            } catch (VKApiParamAlbumIdException $e) {
                throw new Exception($e->getMessage(), null, $e);
            } catch (VKApiParamHashException $e) {
                throw new Exception($e->getMessage(), null, $e);
            } catch (VKApiParamServerException $e) {
                throw new Exception($e->getMessage(), null, $e);
            } catch (VKApiException $e) {
                throw new Exception($e->getMessage(), null, $e);
            } catch (VKClientException $e) {
                throw new Exception($e->getMessage(), null, $e);
            }

            sleep(1);
        }
        return $uploadedPhoto;
    }
}