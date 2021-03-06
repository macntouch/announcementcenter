<?php
/**
 * @copyright Copyright (c) 2016, Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\AnnouncementCenter\Tests\Notification;

use OCA\AnnouncementCenter\Notification\Notifier;
use OCA\AnnouncementCenter\Manager;
use OCA\AnnouncementCenter\Tests\TestCase;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\L10N\IFactory;

class NotifierTest extends TestCase {
	/** @var Notifier */
	protected $notifier;

	/** @var Manager|\PHPUnit_Framework_MockObject_MockObject */
	protected $manager;
	/** @var IUserManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $userManager;
	/** @var IFactory|\PHPUnit_Framework_MockObject_MockObject */
	protected $factory;
	/** @var IL10N|\PHPUnit_Framework_MockObject_MockObject */
	protected $l;

	protected function setUp() {
		parent::setUp();

		$this->manager = $this->getMockBuilder('OCA\AnnouncementCenter\Manager')
			->disableOriginalConstructor()
			->getMock();
		$this->userManager = $this->getMockBuilder('OCP\IUserManager')
			->disableOriginalConstructor()
			->getMock();
		$this->l = $this->getMockBuilder('OCP\IL10N')
			->disableOriginalConstructor()
			->getMock();
		$this->l->expects($this->any())
			->method('t')
			->willReturnCallback(function($string, $args) {
				return vsprintf($string, $args);
			});
		$this->factory = $this->getMockBuilder('OCP\L10N\IFactory')
			->disableOriginalConstructor()
			->getMock();
		$this->factory->expects($this->any())
			->method('get')
			->willReturn($this->l);

		$this->notifier = new Notifier(
			$this->manager,
			$this->factory,
			$this->userManager
		);
	}

	/**
	 * @expectedException \InvalidArgumentException
	 */
	public function testPrepareWrongApp() {
		/** @var \OCP\Notification\INotification|\PHPUnit_Framework_MockObject_MockObject $notification */
		$notification = $this->getMockBuilder('OCP\Notification\INotification')
			->disableOriginalConstructor()
			->getMock();

		$notification->expects($this->once())
			->method('getApp')
			->willReturn('notifications');
		$notification->expects($this->never())
			->method('getSubject');

		$this->notifier->prepare($notification, 'en');
	}

	/**
	 * @expectedException \InvalidArgumentException
	 */
	public function testPrepareWrongSubject() {
		/** @var \OCP\Notification\INotification|\PHPUnit_Framework_MockObject_MockObject $notification */
		$notification = $this->getMockBuilder('OCP\Notification\INotification')
			->disableOriginalConstructor()
			->getMock();

		$notification->expects($this->once())
			->method('getApp')
			->willReturn('announcementcenter');
		$notification->expects($this->once())
			->method('getSubject')
			->willReturn('wrong subject');

		$this->notifier->prepare($notification, 'en');
	}

	/**
	 * @return \OCP\IUser|\PHPUnit_Framework_MockObject_Builder_InvocationMocker
	 */
	protected function getUserMock() {
		$user = $this->getMock('OCP\IUser');
		$user->expects($this->once())
			->method('getDisplayName')
			->willReturn('Author');
		return $user;
	}

	public function dataPrepare() {
		$message = "message\nmessage message message message message message message message message message message messagemessagemessagemessagemessagemessagemessage";
		return [
			['author', 'subject', 'message', 42, null, 'author announced “subject”', 'message'],
			['author1', 'subject', 'message', 42, $this->getUserMock(), 'Author announced “subject”', 'message'],
			['author2', "subject\nsubject", $message, 21, null, 'author2 announced “subject subject”', $message],
		];
	}

	/**
	 * @dataProvider dataPrepare
	 *
	 * @param string $author
	 * @param string $subject
	 * @param string $message
	 * @param int $objectId
	 * @param \OCP\IUser $userObject
	 * @param string $expectedSubject
	 * @param string $expectedMessage
	 */
	public function testPrepare($author, $subject, $message, $objectId, $userObject, $expectedSubject, $expectedMessage) {
		/** @var \OCP\Notification\INotification|\PHPUnit_Framework_MockObject_MockObject $notification */
		$notification = $this->getMockBuilder('OCP\Notification\INotification')
			->disableOriginalConstructor()
			->getMock();

		$notification->expects($this->once())
			->method('getApp')
			->willReturn('announcementcenter');
		$notification->expects($this->once())
			->method('getSubject')
			->willReturn('announced');
		$notification->expects($this->once())
			->method('getSubjectParameters')
			->willReturn([$author]);
		$notification->expects($this->once())
			->method('getObjectId')
			->willReturn($objectId);

		$this->manager->expects($this->once())
			->method('getAnnouncement')
			->with($objectId, false)
			->willReturn([
				'subject' => $subject,
				'message' => $message,
			]);
		$this->userManager->expects($this->once())
			->method('get')
			->with($author)
			->willReturn($userObject);

		$notification->expects($this->once())
			->method('setParsedMessage')
			->with($expectedMessage)
			->willReturnSelf();
		$notification->expects($this->once())
			->method('setParsedSubject')
			->with($expectedSubject)
			->willReturnSelf();

		$return = $this->notifier->prepare($notification, 'en');

		$this->assertEquals($notification, $return);
	}
}
