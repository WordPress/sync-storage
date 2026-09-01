# Playground demo

Everything here exists to boot a demonstration of this plugin in
[WordPress Playground](https://playground.wordpress.net/). None of it ships in
the plugin zip, and none of it goes to core with the storage layer.

That is the whole reason the directory exists. `lib/` is the code under
discussion for core and `tests/` travels with it; both are held to that
standard. The files below are staging: they fabricate peers that do not exist,
reach past a server timeout on purpose, and raise a limit the editor sets for
good reason. Useful for showing the thing working, wrong anywhere else.

`.distignore` excludes `demo`, so a new file added here cannot reach the zip by
being forgotten about.

| File | Role |
| --- | --- |
| `blueprint.json` | Solo editor. The README badge points here. |
| `blueprint-5.json` | Five seeded collaborators. |
| `blueprint-40.json` | Forty seeded collaborators, for render scale. |
| `demo-seeder.php` | Creates the users and writes their awareness. |
| `mu-plugins/rtc-demo-helper.php` | Keeps the demo standing while it runs. |

The collaborator blueprints are only reachable through the Playground comment
on a pull request. `Playground Preview Publish` rewrites every asset URL in
them to that PR's own build, so the versions committed here point at `main` and
serve as the shape the workflow validates, not as links to click.

## Test fixtures are not demo code

`tests/e2e/mu-plugins/` holds mu-plugins too, and the difference matters. Those
are fixtures a Playwright spec loads to observe the plugin — they assert. These
pretend. A file that fakes state so a human can look at it belongs here; a file
that measures real behaviour belongs with the tests.
