# Changelog

All notable changes to this project will be documented in this file, in reverse chronological order by release.

## 1.5.0 - 2018-05-03

### Added

- [#158](https://github.com/zfcampus/zf-hal/pull/158) adds support for PHP 7.1 and 7.2.

- [#167](https://github.com/zfcampus/zf-hal/pull/167) adds a new event, `fromLink.pre`, triggered from the `ZF\Hal\Plugin\Hal::fromLink` method.
  This event can be used in conjunction with `ZF\Rest\RestController::create()` to manipulate the generated
  link for purpose of modifying it for the `Link` HTTP response header.

### Changed

- [#163](https://github.com/zfcampus/zf-hal/pull/163) updates `ZF\Hal\Link\Link` to implement the PSR-13 `LinkInterface`, and modifies
  some internals to make use of its idempotency.

- [#165](https://github.com/zfcampus/zf-hal/pull/165) modifies the `JsonSerializableEntity` to implement the native PHP `JsonSerializable`
  interface instead of the polyfill from zend-stdlib, as all versions of PHP we support
  provide that interface in default installs now.

### Deprecated

- [#163](https://github.com/zfcampus/zf-hal/pull/163) both adds and deprecates the method `ZF\Hal\Link\LinkCollection::idempotentAdd()`;
  in version 3, if released, that method will replace the `add()` method. Its
  internals largely replace functionality in `ZF\Hal\Plugin\Hal::injectPropertyAsLink()`.

- [#163](https://github.com/zfcampus/zf-hal/pull/163) deprecates the "url" key when creating a new link from an array, in
  favor of an "href" key.

### Removed

- [#158](https://github.com/zfcampus/zf-hal/pull/158) removes support for HHVM.

### Fixed

- [#161](https://github.com/zfcampus/zf-hal/pull/161) fixes initialization of the `hal` view helper, ensuring it receives an
  event manager instance within its factory. Previously, listeners attached within delegator
  factories could be overwritten.

## 1.4.2 - 2016-07-28

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [#151](https://github.com/zfcampus/zf-hal/pull/151) updates the
  `HalControllerPluginFactory` to work correctly under v2 releases of
  zend-servicemanager.

## 1.4.1 - 2016-07-27

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [#149](https://github.com/zfcampus/zf-hal/pull/149) updates the zend-hydrator
  dependency to allow either the 1.1 or 2.2 series, allowing usage with
  zend-stdlib v2 releases.

## 1.4.0 - 2016-07-07

### Added

- [#142](https://github.com/zfcampus/zf-hal/pull/142) and
  [#145](https://github.com/zfcampus/zf-hal/pull/145) add support for Zend
  Framework v3 component releases, retaining support for v2 versions as well;
  specifically, zend-eventmanager, zend-mvc, zend-stdlib, and
  zend-servicemanager v3 may now be used with this module.
- [#142](https://github.com/zfcampus/zf-hal/pull/142) and
  [#145](https://github.com/zfcampus/zf-hal/pull/145) add support for PHP 7.
- [#99](https://github.com/zfcampus/zf-hal/pull/99) adds accessors for the
  `$entity` and `$id` properties of `ZF\Hal\Entity`.
- [#124](https://github.com/zfcampus/zf-hal/pull/124) adds a new interface
  `ZF\Hal\Link\SelfLinkInjectorInterface` and default implementation
  `ZF\Hal\Link\SelfLinkInjector`; these are now used as collaborators to the
  `Hal` plugin to simplify internal logic, and allow users to provide alternate
  strategies for generating the `self` relational link.
- [#125](https://github.com/zfcampus/zf-hal/pull/125) adds a new service,
  `ZF\Hal\Link\LinkUrlBuilder`. This class composes the `ServerUrl` and `Url`
  view helpers in order to provide the functionality required to build a
  route-based link URL. The `Hal` plugin now consumes this instead of
  implementing the logic internally.

  The upshot is: you can replace the URL generation semantics for your
  application entirely by pointing the service to your own implementation.
- [#125](https://github.com/zfcampus/zf-hal/pull/125) adds service factories for
  each of the `LinkExtractor` and `LinkCollectionExtractor`, which now allows
  users to provide substitutions for their functionality. (Extractors pull links
  and link collections in order to generate the relational links for a HAL-JSON
  payload.)
- [#139](https://github.com/zfcampus/zf-hal/pull/139) adds the new method
  `Hal::resetEntityHashStack()`; this method can be used when rendering multiple
  responses and/or payloads within the same request cycle, in order to allow
  re-using the same entity instances (normally, they would be skipped when
  discovered on subsequent iterations).

### Deprecated

- [#99](https://github.com/zfcampus/zf-hal/pull/99) deprecates usage of property
  access on `ZF\Hal\Entity` to retrieve the identifier and underlying entity
  instance.
- [#125](https://github.com/zfcampus/zf-hal/pull/125) deprecates the usage of
  `Hal::setServerUrlHelper()` and `Hal::setUrlHelper()`; these will each now
  raise an exception indicating the user should use a `LinkUrlBuilder` for URL
  generation instead.
- [#125](https://github.com/zfcampus/zf-hal/pull/125) deprecates passing a
  `ServerUrlHelper` and `UrlHelper` to the constructor of
  `ZF\Hal\Exctractor\LinkExtractor`; it now expects a `LinkUrlBuilder` instance
  instead. (This class is primarily an internal detail of the `Hal` plugin.)

### Removed

- [#145](https://github.com/zfcampus/zf-hal/pull/145) removes support for PHP 5.5.

### Fixed

- Nothing.

## 1.3.1 - 2016-07-07

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [#111](https://github.com/zfcampus/zf-hal/pull/111) removes some code errantly
  left in a comment from a previous merge conflict.
- [#112](https://github.com/zfcampus/zf-hal/pull/112) removes conditionals based
  on PHP 5.4, as the minimum version is now 5.5.
- [#127](https://github.com/zfcampus/zf-hal/pull/127) fixes an issue in the
  `HalJsonStrategy` plugin whereby the wrong `Content-Type` header was being
  used when an `ApiProblem` response was handled; these now correctly return
  `application/problem+json` instead of `application/hal+json`.

## 1.3.0 - 2015-09-22

### Added

- [#123](https://github.com/zfcampus/zf-hal/pull/123) updates the component
  to use zend-hydrator for hydrator functionality; this provides forward
  compatibility with zend-hydrator, and backwards compatibility with
  hydrators from older versions of zend-stdlib.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.

## 1.2.1 - 2015-09-22

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [#122](https://github.com/zfcampus/zf-hal/pull/122) updates the
  zend-stdlib dependency to reference `>=2.5.0,<2.7.0` to ensure hydrators
  will work as expected following extraction of hydrators to the zend-hydrator
  repository.
