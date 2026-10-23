# CHANGELOG

## 1.0.3 (2026-10-23)

- Declares that none of its options hold a secret, by answering the `hampel_admin_api_redaction`
  event. A forum that collects its configuration through that event now sees what the four text
  options are set to, rather than only whether they are set; where nothing fires the event,
  nothing changes

## 1.0.2 (2026-09-22)

- Requires XenForo 2.2.0 or later
- The default text for the "Body before" option no longer refers to a list below it. When no
  upgrades are available there is no list, and this add-on's blocks take the place of XenForo's
  "There are currently no purchasable user upgrades" message. Existing installs keep whatever text
  they have set

## 1.0.1 (2025-12-11)

- Remove files left behind by an upgrade, on XenForo 2.3 and later

## 1.0.0 (2020-06-13)

- First release
