<?php

namespace Friendica\Module\Notifications;

use Friendica\Content\ContactSelector;
use Friendica\Content\Nav;
use Friendica\Core\Protocol;
use Friendica\Core\Renderer;
use Friendica\Database\DBA;
use Friendica\DI;
use Friendica\Module\BaseNotifications;
use Friendica\Object\Notification\Introduction;

/**
 * Prints notifications about introduction
 */
class Introductions extends BaseNotifications
{
	/**
	 * @inheritDoc
	 */
	public static function getNotifications()
	{
		$id  = (int)DI::args()->get(2, 0);
		$all = DI::args()->get(2) == 'all';

		$notifications = [
			'ident'        => 'introductions',
			'notifications' => DI::factNotIntro()->getIntroList($all, self::$firstItemNum, self::ITEMS_PER_PAGE, $id),
		];

		return [
			'header'        => DI::l10n()->t('Notifications'),
			'notifications' => $notifications,
		];
	}

	public static function content(array $parameters = [])
	{
		Nav::setSelected('introductions');

		$all = DI::args()->get(2) == 'all';

		$notificationContent   = [];
		$notificationNoContent = '';

		$notificationResult = self::getNotifications();
		$notifications      = $notificationResult['notifications'] ?? [];
		$notificationHeader = $notificationResult['header'] ?? '';

		$notificationSuggestions = Renderer::getMarkupTemplate('notifications/suggestions.tpl');
		$notificationTemplate    = Renderer::getMarkupTemplate('notifications/intros.tpl');

		// The link to switch between ignored and normal connection requests
		$notificationShowLink = [
			'href' => (!$all ? 'notifications/intros/all' : 'notifications/intros'),
			'text' => (!$all ? DI::l10n()->t('Show Ignored Requests') : DI::l10n()->t('Hide Ignored Requests')),
		];

		// Loop through all introduction notifications.This creates an array with the output html for each
		// introduction
		/** @var Introduction $notification */
		foreach ($notifications['notifications'] as $notification) {

			// There are two kind of introduction. Contacts suggested by other contacts and normal connection requests.
			// We have to distinguish between these two because they use different data.
			switch ($notification['label']) {
				case 'friend_suggestion':
					$notificationContent[] = Renderer::replaceMacros($notificationSuggestions, [
						'$type'                 => $notification->getLabel(),
						'str_notification_type' => DI::l10n()->t('Notification type:'),
						'str_type'              => $notification->getType(),
						'$intro_id'             => $notification->getIntroId(),
						'$lbl_madeby'           => DI::l10n()->t('Suggested by:'),
						'$madeby'               => $notification->getMadeBy(),
						'$madeby_url'           => $notification->getMadeByUrl(),
						'$madeby_zrl'           => $notification->getMadeByZrl(),
						'$madeby_addr'          => $notification->getMadeByAddr(),
						'$contact_id'           => $notification->getContactId(),
						'$photo'                => $notification->getPhoto(),
						'$fullname'             => $notification->getName(),
						'$url'                  => $notification->getUrl(),
						'$zrl'                  => $notification->getZrl(),
						'$lbl_url'              => DI::l10n()->t('Profile URL'),
						'$addr'                 => $notification->getAddr(),
						'$hidden'               => ['hidden', DI::l10n()->t('Hide this contact from others'), $notification->isHidden(), ''],
						'$knowyou'              => $notification->getKnowYou(),
						'$approve'              => DI::l10n()->t('Approve'),
						'$note'                 => $notification->getNote(),
						'$request'              => $notification->getRequest(),
						'$ignore'               => DI::l10n()->t('Ignore'),
						'$discard'              => DI::l10n()->t('Discard'),
					]);
					break;

				// Normal connection requests
				default:
					$friend_selected = (($notification->getNetwork() !== Protocol::OSTATUS) ? ' checked="checked" ' : ' disabled ');
					$fan_selected    = (($notification->getNetwork() === Protocol::OSTATUS) ? ' checked="checked" disabled ' : '');

					$lbl_knowyou = '';
					$knowyou     = '';
					$helptext    = '';
					$helptext2   = '';
					$helptext3   = '';

					if ($notification->getNetwork() === Protocol::DFRN) {
						$lbl_knowyou = DI::l10n()->t('Claims to be known to you: ');
						$knowyou     = ($notification->getKnowYou() ? DI::l10n()->t('yes') : DI::l10n()->t('no'));
						$helptext    = DI::l10n()->t('Shall your connection be bidirectional or not?');
						$helptext2   = DI::l10n()->t('Accepting %s as a friend allows %s to subscribe to your posts, and you will also receive updates from them in your news feed.', $notification['name'], $notification['name']);
						$helptext3   = DI::l10n()->t('Accepting %s as a subscriber allows them to subscribe to your posts, but you will not receive updates from them in your news feed.', $notification['name']);
					} elseif ($notification->getNetwork() === Protocol::DIASPORA) {
						$helptext  = DI::l10n()->t('Shall your connection be bidirectional or not?');
						$helptext2 = DI::l10n()->t('Accepting %s as a friend allows %s to subscribe to your posts, and you will also receive updates from them in your news feed.', $notification['name'], $notification['name']);
						$helptext3 = DI::l10n()->t('Accepting %s as a sharer allows them to subscribe to your posts, but you will not receive updates from them in your news feed.', $notification['name']);
					}

					$dfrn_tpl  = Renderer::getMarkupTemplate('notifications/netfriend.tpl');
					$dfrn_text = Renderer::replaceMacros($dfrn_tpl, [
						'$intro_id'        => $notification->getIntroId(),
						'$friend_selected' => $friend_selected,
						'$fan_selected'    => $fan_selected,
						'$approve_as1'     => $helptext,
						'$approve_as2'     => $helptext2,
						'$approve_as3'     => $helptext3,
						'$as_friend'       => DI::l10n()->t('Friend'),
						'$as_fan'          => (($notification->getNetwork() == Protocol::DIASPORA) ? DI::l10n()->t('Sharer') : DI::l10n()->t('Subscriber')),
					]);

					$contact = DBA::selectFirst('contact', ['network', 'protocol'], ['id' => $notification->getContactId()]);

					if (($contact['network'] != Protocol::DFRN) || ($contact['protocol'] == Protocol::ACTIVITYPUB)) {
						$action = 'follow_confirm';
					} else {
						$action = 'dfrn_confirm';
					}

					$header = $notification['name'];

					if ($notification['addr'] != '') {
						$header .= ' <' . $notification['addr'] . '>';
					}

					$header .= ' (' . ContactSelector::networkToName($notification->getNetwork(), $notification->getUrl()) . ')';

					if ($notification->getNetwork() != Protocol::DIASPORA) {
						$discard = DI::l10n()->t('Discard');
					} else {
						$discard = '';
					}

					$notificationContent[] = Renderer::replaceMacros($notificationTemplate, [
						'$type'                 => $notification->getLabel(),
						'$header'               => $header,
						'str_notification_type' => DI::l10n()->t('Notification type:'),
						'str_type'              => $notification->getType(),
						'$dfrn_text'            => $dfrn_text,
						'$dfrn_id'              => $notification->getDfrnId(),
						'$uid'                  => $notification->getUid(),
						'$intro_id'             => $notification->getIntroId(),
						'$contact_id'           => $notification->getContactId(),
						'$photo'                => $notification->getPhoto(),
						'$fullname'             => $notification->getName(),
						'$location'             => $notification->getLocation(),
						'$lbl_location'         => DI::l10n()->t('Location:'),
						'$about'                => $notification->getAbout(),
						'$lbl_about'            => DI::l10n()->t('About:'),
						'$keywords'             => $notification->getKeywords(),
						'$lbl_keywords'         => DI::l10n()->t('Tags:'),
						'$gender'               => $notification->getGender(),
						'$lbl_gender'           => DI::l10n()->t('Gender:'),
						'$hidden'               => ['hidden', DI::l10n()->t('Hide this contact from others'), ($notification['hidden'] == 1), ''],
						'$url'                  => $notification->getUrl(),
						'$zrl'                  => $notification->getZrl(),
						'$lbl_url'              => DI::l10n()->t('Profile URL'),
						'$addr'                 => $notification->getAddr(),
						'$lbl_knowyou'          => $lbl_knowyou,
						'$lbl_network'          => DI::l10n()->t('Network:'),
						'$network'              => ContactSelector::networkToName($notification->getNetwork(), $notification->getUrl()),
						'$knowyou'              => $knowyou,
						'$approve'              => DI::l10n()->t('Approve'),
						'$note'                 => $notification->getNote(),
						'$ignore'               => DI::l10n()->t('Ignore'),
						'$discard'              => $discard,
						'$action'               => $action,
					]);
					break;
			}
		}

		if (count($notifications['notifications']) == 0) {
			info(DI::l10n()->t('No introductions.') . EOL);
			$notificationNoContent = DI::l10n()->t('No more %s notifications.', $notifications['ident']);
		}

		return self::printContent($notificationHeader, $notificationContent, $notificationNoContent, $notificationShowLink);
	}
}
