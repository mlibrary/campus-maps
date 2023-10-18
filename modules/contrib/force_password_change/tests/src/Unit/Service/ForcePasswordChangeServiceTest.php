<?php

namespace Drupal\Tests\force_password_change\Unit\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\force_password_change\Mapper\ForcePasswordChangeMapperInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\force_password_change_service_test\Service\ForcePasswordChangeServiceTest as ForcePasswordChangeService;
use Drupal\user\UserDataInterface;

/**
 * @coversDefaultClass \Drupal\force_password_change\Service\ForcePasswordChangeService
 * @group force_password_change
 */
class ForcePasswordChangeServiceTest extends UnitTestCase {

  /**
   * The test container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected $container;

  /**
   * The Force Password Change data mapper.
   */
  protected $mapper;

  /**
   * The current user.
   */
  protected $currentUser;

  /**
   * The config factory.
   */
  protected $configFactory;

  /**
   * The user data service.
   */
  protected $userData;

  /**
   * The Force Password Change service object.
   */
  protected $forcePasswordChangeService;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    $this->container = new ContainerBuilder();
    \Drupal::setContainer($this->container);

    $this->mapper = $this->createMock(ForcePasswordChangeMapperInterface::class);
    $this->mapper->expects($this->any())
      ->method('getUserCreatedTime')
      ->willReturn(1000000);

    $this->currentUser = $this->createMock(AccountProxyInterface::class);
    $this->currentUser->expects($this->any())
      ->method('id')
      ->willReturn(1);
    $this->currentUser->expects($this->any())
      ->method('getRoles')
      ->willReturn(['authenticated']);

    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);

    $this->userData = $this->createMock(UserDataInterface::class);

    $this->time = $this->createMock(TimeInterface::class);
    $this->time->expects($this->any())
      ->method('getRequestTime')
      ->willReturn(1000101);

    $this->forcePasswordChangeService = new ForcePasswordChangeService($this->mapper, $this->currentUser, $this->configFactory, $this->userData, $this->time);
  }

  /**
   * @covers ::checkForForce
   */
  public function testCheckForForce() {
    $this->userData->expects($this->at(0))
      ->method('get')
      ->with('force_password_change', 1, 'pending_force')
      ->willReturn(TRUE);

    $value = $this->forcePasswordChangeService->checkForForce();

    $this->assertEquals('admin_forced', $value, 'Proper value returned when admin has forced user to change their password');

    $this->configFactory->expects($this->at(0))
      ->method('get')
      ->with('force_password_change.settings')
      ->willReturn($this->configFactory);
    $this->configFactory->expects($this->at(1))
      ->method('get')
      ->with('expire_password')
      ->willReturn(TRUE);

    $this->userData->expects($this->at(1))
      ->method('get')
      ->with('force_password_change', $this->currentUser->id(), 'last_change')
      ->willReturn(1000000);

    $this->mapper->expects($this->at(1))
      ->method('getExpiryTimeFromRoles')
      ->willReturn(100);

    $value = $this->forcePasswordChangeService->checkForForce();

    $this->assertEquals('expired', $value, 'Proper value returned when admin has forced user to change their password');

    $this->configFactory->expects($this->at(0))
      ->method('get')
      ->with('force_password_change.settings')
      ->willReturn($this->configFactory);
    $this->configFactory->expects($this->at(1))
      ->method('get')
      ->with('expire_password')
      ->willReturn(TRUE);

    $this->userData->expects($this->at(1))
      ->method('get')
      ->with('force_password_change', $this->currentUser->id(), 'last_change')
      ->willReturn(1000001);

    $this->mapper->expects($this->at(1))
      ->method('getExpiryTimeFromRoles')
      ->willReturn(100);

    $value = $this->forcePasswordChangeService->checkForForce();

    $this->assertFalse($value, 'FALSE correctly returned when user has changed their password within the required time');

    $this->configFactory->expects($this->at(0))
      ->method('get')
      ->with('force_password_change.settings')
      ->willReturn($this->configFactory);
    $this->configFactory->expects($this->at(1))
      ->method('get')
      ->with('expire_password')
      ->willReturn(TRUE);

    $this->userData->expects($this->at(1))
      ->method('get')
      ->with('force_password_change', $this->currentUser->id(), 'last_change')
      ->willReturn(FALSE);

    $this->mapper->expects($this->at(1))
      ->method('getExpiryTimeFromRoles')
      ->with(['authenticated'])
      ->willReturn(99);

    $value = $this->forcePasswordChangeService->checkForForce();

    $this->assertEquals('expired', $value, 'Proper value returned when password has expired');

    $this->configFactory->expects($this->at(0))
      ->method('get')
      ->with('force_password_change.settings')
      ->willReturn($this->configFactory);
    $this->configFactory->expects($this->at(1))
      ->method('get')
      ->with('expire_password')
      ->willReturn(TRUE);

    $this->userData->expects($this->at(1))
      ->method('get')
      ->with('force_password_change', $this->currentUser->id(), 'last_change')
      ->willReturn(FALSE);

    $this->mapper->expects($this->at(1))
      ->method('getExpiryTimeFromRoles')
      ->willReturn(101);

    $value = $this->forcePasswordChangeService->checkForForce();

    $this->assertFalse($value, 'FALSE correctly returned when users password has not expired');

    $this->configFactory->expects($this->at(0))
      ->method('get')
      ->with('force_password_change.settings')
      ->willReturn($this->configFactory);
    $this->configFactory->expects($this->at(1))
      ->method('get')
      ->with('expire_password')
      ->willReturn(TRUE);

    $this->userData->expects($this->at(1))
      ->method('get')
      ->with('force_password_change', $this->currentUser->id(), 'last_change')
      ->willReturn(FALSE);

    $this->mapper->expects($this->at(1))
      ->method('getExpiryTimeFromRoles')
      ->willReturn(100);

    $value = $this->forcePasswordChangeService->checkForForce();

    $this->assertEquals('expired', $value, 'Proper value returned when password has expired and user has never changed their password');

    $this->configFactory->expects($this->at(0))
      ->method('get')
      ->with('force_password_change.settings')
      ->willReturn($this->configFactory);
    $this->configFactory->expects($this->at(1))
      ->method('get')
      ->with('expire_password')
      ->willReturn(FALSE);

    $this->userData->expects($this->at(0))
      ->method('get')
      ->with('force_password_change', 1, 'pending_force')
      ->willReturn(FALSE);

    $value = $this->forcePasswordChangeService->checkForForce();

    $this->assertFalse($value, 'FALSE correctly returned when has no pending force, nor are their role passwords set to expire');
  }

  /**
   * @covers ::getLastChangeForRole
   */
  public function testGetLastChangeForRole() {
    $this->mapper->expects($this->at(0))
      ->method('getLastChangeForRole')
      ->with('admin')
      ->willReturn(100);

    $rid = 'admin';
    $value = $this->forcePasswordChangeService->getLastChangeForRole($rid);

    $this->assertEquals(100, $value, 'The last changed value is properly returned');
  }

  /**
   * @covers ::getUserCountForRole
   */
  public function testGetUserCountForRole() {
    $this->mapper->expects($this->at(0))
      ->method('getUserCountForRole')
      ->with(FALSE)
      ->willReturn(100);

    $rid = 'authenticated';
    $value = $this->forcePasswordChangeService->getUserCountForRole($rid);

    $this->assertEquals(100, $value, 'The user count value is properly returned for the authenticated role');

    $this->mapper->expects($this->at(0))
      ->method('getUserCountForRole')
      ->with('admin')
      ->willReturn(101);

    $rid = 'admin';
    $value = $this->forcePasswordChangeService->getUserCountForRole($rid);

    $this->assertEquals(101, $value, 'The user count value is properly returned for the admin role');
  }

  /**
   * @covers ::getPendingUsersForRole
   */
  public function testGetPendingUsersForRole() {
    $this->mapper->expects($this->at(0))
      ->method('getPendingUserIds')
      ->with(FALSE)
      ->willReturn([1, 2, 3]);

    $rid = 'authenticated';
    $value = $this->forcePasswordChangeService->getPendingUsersForRole($rid, TRUE);

    $this->assertEquals(3, $value, 'The pending user count value is properly returned for the authenticated role');

    $this->mapper->expects($this->at(0))
      ->method('getPendingUserIds')
      ->with('admin')
      ->willReturn([1, 2, 3, 4]);

    $rid = 'admin';
    $value = $this->forcePasswordChangeService->getPendingUsersForRole($rid, TRUE);

    $this->assertEquals(4, $value, 'The pending user count value is properly returned for the admin role');

    $this->mapper->expects($this->at(0))
      ->method('getPendingUserIds')
      ->with(FALSE)
      ->willReturn([1, 2, 3]);

    $rid = 'authenticated';
    $value = $this->forcePasswordChangeService->getPendingUsersForRole($rid);

    $this->assertCount(3, $value, 'The correct number of pending users was returned for the authenticated role');

    $this->mapper->expects($this->at(0))
      ->method('getPendingUserIds')
      ->with('admin')
      ->willReturn([1, 2, 3, 4]);

    $rid = 'admin';
    $value = $this->forcePasswordChangeService->getPendingUsersForRole($rid);

    $this->assertCount(4, $value, 'The correct number of pending users was returned for the admin role');
  }

  /**
   * @covers ::getNonPendingUsersForRole
   */
  public function testGetNonPendingUsersForRole() {
    $this->mapper->expects($this->at(0))
      ->method('getNonPendingUserIds')
      ->with(FALSE)
      ->willReturn([1, 2, 3]);

    $rid = 'authenticated';
    $value = $this->forcePasswordChangeService->getNonPendingUsersForRole($rid, TRUE);

    $this->assertCount(3, $value, 'The correct number of non-pending users was returned for the authenticated role');

    $this->mapper->expects($this->at(0))
      ->method('getNonPendingUserIds')
      ->with('admin')
      ->willReturn([1, 2, 3, 4]);

    $rid = 'admin';
    $value = $this->forcePasswordChangeService->getNonPendingUsersForRole($rid, TRUE);

    $this->assertCount(4, $value, 'The correct number of non-pending users was returned for the admin role');
  }

  /**
   * @covers ::getRoleExpiryTimePeriods
   */
  public function testgetRoleExpiryTimePeriods() {
    $this->mapper->expects($this->at(0))
      ->method('getRoleExpiryTimePeriods')
      ->willReturn(['authenticated' => 1, 'admin' => 2]);

    $value = $this->forcePasswordChangeService->getRoleExpiryTimePeriods();

    $this->assertArrayHasKey('authenticated', $value, 'The authenticated key exists in the list of role expiry dates');
    $this->assertEquals(1, $value['authenticated'], 'The expiration value for the authenticated user is correct');
    $this->assertArrayHasKey('admin', $value, 'The authenticated key exists in the list of role expiry dates');
    $this->assertEquals(2, $value['admin'], 'The expiration value for the admin user is correct');
  }

  /**
   * @covers ::getUsersForRole
   */
  public function testGetUsersForRole() {
    $role = 'authenticated';
    $this->mapper->expects($this->at(0))
      ->method('getUserIdsForRole')
      ->with($role)
      ->willReturn([1, 2]);

    $value = $this->forcePasswordChangeService->getUsersForRole($role, TRUE);

    $this->assertCount(2, $value, 'The correct number of User IDs was returned for the authenticated role');

    $role = 'admin';
    $this->mapper->expects($this->at(0))
      ->method('getUserIdsForRole')
      ->with($role)
      ->willReturn([1, 2, 3]);

    $value = $this->forcePasswordChangeService->getUsersForRole($role, TRUE);

    $this->assertCount(3, $value, 'The correct number of User IDs was returned for the admin role');

    $role = 'authenticated';
    $this->mapper->expects($this->at(0))
      ->method('getUserIdsForRole')
      ->with($role)
      ->willReturn([1, 2]);

    $value = $this->forcePasswordChangeService->getUsersForRole($role, FALSE);

    $this->assertCount(2, $value, 'The correct number of Users was returned for the authenticated role');
    $this->assertEquals('user1', $value[1], 'The correct username was returned for user 1');
    $this->assertEquals('user2', $value[2], 'The correct username was returned for user 2');

    $role = 'admin';
    $this->mapper->expects($this->at(0))
      ->method('getUserIdsForRole')
      ->with($role)
      ->willReturn([3, 4]);

    $value = $this->forcePasswordChangeService->getUsersForRole($role, FALSE);

    $this->assertCount(2, $value, 'The correct number of Users was returned for the admin role');
    $this->assertEquals('user3', $value[3], 'The correct username was returned for user 3');
    $this->assertEquals('user4', $value[4], 'The correct username was returned for user 4');
  }

  /**
   * @covers ::getFirstTimeLoginUids
   * @dataProvider getFirstTimeLoginUidsDataProvider
   */
  public function testGetFirstTimeLoginUids($uids) {
    $this->mapper->expects($this->at(0))
      ->method('getFirstTimeLoginUids')
      ->willReturn($uids);

    $first_time_login_uids = $this->forcePasswordChangeService->getFirstTimeLoginUids();

    $this->assertSame($uids, $first_time_login_uids, 'The correct first time login UIDs were returned');
  }

  /**
   * Data provider for getFirstTimeLoginUids()
   */
  public function getFirstTimeLoginUidsDataProvider() {
    return [
    [[]],
    [[1, 2, 3]],
    ];
  }

}
