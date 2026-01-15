<?php

namespace Drupal\session_based_temp_store;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Session\SessionConfigurationInterface;
use Drupal\Core\TempStore\TempStoreException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Stores and retrieves temporary data for a given owner.
 *
 * A SessionBasedTempStore can be used as like PrivateTempStore to make
 * temporary, non-cache data available across requests. The data for the
 * PrivateTempStore is stored in one key/value collection.
 * SessionBasedTempStore data expires automatically after a given timeframe.
 *
 * The SessionBasedTempStore differs from the PrivateTempStore in that it can
 * store data based on the user session but without Drupal cookie session. It
 * means that you can use this storage to save data for anonymous user without
 * breaking such things like Varnish.
 */
class SessionBasedTempStore {

  /**
   * The key/value storage object used for this data.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface
   */
  protected $storage;

  /**
   * The lock object used for this data.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lockBackend;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The session configuration.
   *
   * @var \Drupal\Core\Session\SessionConfigurationInterface
   */
  protected $sessionConfiguration;

  /**
   * The time to live for items in seconds.
   *
   * By default, data is stored for one week (604800 seconds) before expiring.
   *
   * @var int
   */
  protected $expire;

  /**
   * The path on the server in which the cookie will be available on.
   *
   * By default the value is to '/'.
   *
   * @var string
   */
  protected $path;

  /**
   * The name of a cookie which will hold the storage ID.
   *
   * By default the value is to 'SESSbasedTempStoreId'.
   *
   * @var string
   */
  protected $cookieName;

  /**
   * Constructs a new object for accessing data from a key/value store.
   *
   * @param \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface $storage
   *   The key/value storage object used for this data. Each storage object
   *   represents a particular collection of data and will contain any number
   *   of key/value pairs.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock_backend
   *   The lock object used for this data.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Session\SessionConfigurationInterface $session_configuration
   *   The session configuration interface.
   * @param string $cookie_name
   *   The name of a cookie which will hold the storage ID.
   * @param int $expire
   *   The time to live for items, in seconds.
   * @param string $path
   *   The path on the server in which the cookie will be available on.
   *   If set to '/', the cookie will be available within the entire domain.
   *   If set to '/foo/', the cookie will only be available
   *   within the /foo/ directory and all sub-directories such as /foo/bar/
   *   of domain. By default the value is to '/'.
   */
  public function __construct(
    KeyValueStoreExpirableInterface $storage,
    LockBackendInterface $lock_backend,
    RequestStack $request_stack,
    SessionConfigurationInterface $session_configuration,
    string $cookie_name,
    $expire = 604800,
    $path = '/'
  ) {
    $this->storage = $storage;
    $this->lockBackend = $lock_backend;
    $this->requestStack = $request_stack;
    $this->sessionConfiguration = $session_configuration;
    $this->cookieName = $cookie_name;
    $this->expire = $expire;
    $this->path = $path;
  }

  /**
   * Retrieves a value from this PrivateTempStore for a given key.
   *
   * @param string $key
   *   The key of the data to retrieve.
   *
   * @return mixed
   *   The data associated with the key, or NULL if the key does not exist.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function get($key) {
    $key = $this->createkey($key);
    if (($object = $this->storage->get($key)) && ($object->owner == $this->getOwner())) {
      return $object->data;
    }
  }

  /**
   * Retrieves an array of all values from this PrivateTempStore.
   *
   * @return array
   *   The array of key => value data or empty array.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function getAll() {
    $values = [];
    $owner = $this->getOwner();
    $objects = $this->storage->getAll();

    if (!empty($objects)) {
      foreach ($objects as $key => $object) {
        if ($object->owner == $owner) {
          $key = explode(':', $key);
          $values[$key[1]] = $object->data;
        }
      }
    }
    return $values;
  }

  /**
   * Stores a particular key/value pair in this PrivateTempStore.
   *
   * @param string $key
   *   The key of the data to store.
   * @param mixed $value
   *   The data to store.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   *   Thrown when a lock for the backend storage could not be acquired.
   */
  public function set($key, $value) {
    $key = $this->createkey($key);
    if (!$this->lockBackend->acquire($key)) {
      $this->lockBackend->wait($key);
      if (!$this->lockBackend->acquire($key)) {
        throw new TempStoreException("Couldn't acquire lock to update item '$key' in '{$this->storage->getCollectionName()}' session based temporary storage.");
      }
    }

    // Note that this service was renamed in Drupal 9.5 and deprecated in 10.
    $version = (int) explode('.', \Drupal::VERSION)[0];
    if ($version < 10) {
      $request_time = (int) $this->requestStack->getMasterRequest()->server->get('REQUEST_TIME');
    }
    else {
      $request_time = (int) $this->requestStack->getMainRequest()->server->get('REQUEST_TIME');
    }

    $value = (object) [
      'owner' => $this->getOwner(),
      'data' => $value,
      'updated' => $request_time,
    ];

    // If the global expiration time is set to 0 (expire at the end of the session),
    // Let's set the DB storage entry expiration time to 24 hours.
    $expire = $this->expire === 0 ? 86400 : $this->expire;

    $this->storage->setWithExpire($key, $value, $expire);
    $this->lockBackend->release($key);
  }

  /**
   * Returns the metadata associated with a particular key/value pair.
   *
   * @param string $key
   *   The key of the data to store.
   *
   * @return mixed
   *   An object with the owner and updated time if the key has a value, or
   *   NULL otherwise.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function getMetadata($key) {
    $key = $this->createkey($key);
    // Fetch the key/value pair and its metadata.
    $object = $this->storage->get($key);
    if ($object) {
      // Don't keep the data itself in memory.
      unset($object->data);
      return $object;
    }
  }

  /**
   * Deletes data from the store for a given key and releases the lock on it.
   *
   * @param string $key
   *   The key of the data to delete.
   *
   * @return bool
   *   TRUE if the object was deleted or does not exist, FALSE if it exists but
   *   is not owned by $this->owner.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   *   Thrown when a lock for the backend storage could not be acquired.
   */
  public function delete($key) {
    $key = $this->createkey($key);
    if (!$object = $this->storage->get($key)) {
      return TRUE;
    }
    elseif ($object->owner != $this->getOwner()) {
      return FALSE;
    }
    if (!$this->lockBackend->acquire($key)) {
      $this->lockBackend->wait($key);
      if (!$this->lockBackend->acquire($key)) {
        throw new TempStoreException("Couldn't acquire lock to delete item '$key' from '{$this->storage->getCollectionName()}' session based temporary storage.");
      }
    }
    $this->storage->delete($key);
    $this->lockBackend->release($key);
    return TRUE;
  }

  /**
   * Deletes all data from the store for the current collection and owner.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function deleteAll() {
    $keys = [];
    foreach ($this->getAll() as $key => $value) {
      $keys[] = $this->createkey($key);
    }
    $this->storage->deleteMultiple($keys);
  }

  /**
   * Gets the current owner based on the current session ID.
   *
   * @return string
   *   The owner.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   *   Thrown when headers have been already send.
   */
  protected function getOwner() {

    if (empty($_COOKIE[$this->cookieName])) {
      $session_already_started = TRUE;
      // Get current session.
      $session = \Drupal::request()->getSession();

      if (!$session->has('core.tempstore.private.owner')) {
        // Remember that we did not have started a session before.
        $session_already_started = FALSE;
        // This generates a unique identifier for the user.
        $session->set('core.tempstore.private.owner', Crypt::randomBytesBase64());
      }

      // Since security is not a problem, let's keep it short.
      $session_store_id = mb_substr($session->get('core.tempstore.private.owner'), 0, 12);

      $request = $this->requestStack->getCurrentRequest();
      $session_options = $this->sessionConfiguration->getOptions($request);

      // .localhost causes problems.
      $cookie_domain = ($session_options['cookie_domain'] == '.localhost') ? ini_get('session.cookie_domain') : $session_options['cookie_domain'];

      // If the site is accessed via SSL, ensure that the cookie is issued
      // with the secure flag.
      $secure = $request->isSecure();

      // setcookie() can only be called when headers are not yet sent.
      if (!headers_sent()) {
        setcookie($this->cookieName, $session_store_id, $this->expirationTime(), $this->path, $cookie_domain, $secure, TRUE);
      }
      else {
        throw new TempStoreException("Couldn't set cookie " . $this->cookieName . " in session based temporary storage. Headers have been already sent.");
      }
      // When sessionStoreId() is called multiple times
      // and there is no $_COOKIE[$this->cookieName] yet.
      // The multiple identical Set-Cookie headers are sent to the client.
      // So we need to set $_COOKIE explicitly.
      $_COOKIE[$this->cookieName] = $session_store_id;

      // Clear session if there was no session before.
      if (!$session_already_started) {
        $session->clear();
      }
    }
    else {
      $session_store_id = $_COOKIE[$this->cookieName];
    }

    return $session_store_id;
  }

  /**
   * Ensures that the key is unique for a user.
   *
   * @param string $key
   *   The key.
   *
   * @return string
   *   The unique key for the user.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  protected function createkey($key) {
    return $this->getOwner() . ':' . $key;
  }

  /**
   * Returns the date/time that the session store will expire.
   *
   * @return int
   *   UNIX time stamp.
   */
  protected function expirationTime() {
    // Allow the cookie to expire at the end of the session.
    if ($this->expire === 0) {
      return $this->expire;
    }

    // Otherwise set the specific expiration time passed in the argument.

    // Note that this service was renamed in Drupal 9.5 and deprecated in 10.
    $version = (int) explode('.', \Drupal::VERSION)[0];
    if ($version < 10) {
      $request_time = (int) $this->requestStack->getMasterRequest()->server->get('REQUEST_TIME');
    }
    else {
      $request_time = (int) $this->requestStack->getMainRequest()->server->get('REQUEST_TIME');
    }

    return $request_time + $this->expire;
  }

}
