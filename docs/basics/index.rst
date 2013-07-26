.. _basics.index:

ZFHal Basics
============

ZFHal allows you to create RESTful JSON APIs that adhere to
:ref:`Hypermedia Application Language <zfhal.hal-primer>`. For error
handling, it uses :ref:`API-Problem <zfhal.error-reporting>`.

The pieces you need to implement, work with, or understand are:

- Writing event listeners for the various ``ZF\Hal\Resource`` events,
  which will be used to either persist resources or fetch resources from
  persistence.

- Writing routes for your resources, and associating them with resources and/or
  ``ZF\Hal\ResourceController``.

- Writing metadata describing your resources, including what routes to associate
  with them.

All API calls are handled by ``ZF\Hal\ResourceController``, which in
turn composes a ``ZF\Hal\Resource`` object and calls methods on it. The
various methods of the controller will return either
``ZF\Hal\ApiProblem`` results on error conditions, or, on success, a
``ZF\Hal\HalResource`` or ``ZF\Hal\HalCollection`` instance; these
are then composed into a ``ZF\Hal\View\RestfulJsonModel``.

If the MVC detects a ``ZF\Hal\View\RestfulJsonModel`` during rendering,
it will select ``ZF\Hal\View\RestfulJsonRenderer``. This, with the help
of the ``ZF\Hal\Plugin\HalLinks`` plugin, will generate an appropriate
payload based on the object composed, and ensure the appropriate Content-Type
header is used.

If a ``ZF\Hal\HalCollection`` is detected, and the renderer determines
that it composes a ``Zend\Paginator\Paginator`` instance, the ``HalLinks``
plugin will also generate pagination relational links to render in the payload.
