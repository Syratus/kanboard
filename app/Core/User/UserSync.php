<?php

namespace Kanboard\Core\User;

use Kanboard\Core\Base;
use Kanboard\Notification\MailNotification;
use Kanboard\Notification\WebNotification;
use Kanboard\Model\UserNotificationFilterModel;

/**
 * User Synchronization
 *
 * @package  user
 * @author   Frederic Guillot
 */
class UserSync extends Base
{
    /**
     * Synchronize user profile
     *
     * @access public
     * @param  UserProviderInterface $user
     * @return array
     */
    public function synchronize(UserProviderInterface $user)
    {
        $profile = $this->userModel->getByExternalId($user->getExternalIdColumn(), $user->getExternalId());
        $properties = UserProperty::getProperties($user);

        if (! empty($profile)) {
            $profile = $this->updateUser($profile, $properties);
        } elseif ($user->isUserCreationAllowed()) {
            $profile = $this->createUser($user, $properties);
        }

        return $profile;
    }

    /**
     * Update user profile
     *
     * @access public
     * @param  array    $profile
     * @param  array    $properties
     * @return array
     */
    private function updateUser(array $profile, array $properties)
    {
        $values = UserProperty::filterProperties($profile, $properties);

        if (! empty($values)) {
            $values['id'] = $profile['id'];
            $result = $this->userModel->update($values);
            return $result ? array_merge($profile, $properties) : $profile;
        }

        return $profile;
    }

    /**
     * Create user
     *
     * @access public
     * @param  UserProviderInterface  $user
     * @param  array                  $properties
     * @return array
     */
    private function createUser(UserProviderInterface $user, array $properties)
    {
        $userId = $this->userModel->create($properties);

        if ($userId === false) {
            $this->logger->error('Unable to create user profile: '.$user->getExternalId());
            return array();
        }
		else
		{
			if (ENABLE_NOTIFICATIONS) {
			    $this->userNotificationModel->enableNotification($userId);	
			    $this->userNotificationTypeModel->saveSelectedTypes($userId, array(MailNotification::TYPE, WebNotification::TYPE));
			    if (NOTIFICATION_FILTER >= 1 && NOTIFICATION_FILTER <= 4) {
				    $this->userNotificationFilterModel->saveFilter($userId, NOTIFICATION_FILTER);
			    }
			}
		}

        return $this->userModel->getById($userId);
    }
}
