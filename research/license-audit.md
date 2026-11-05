# Source and dependency provenance

Initial audit: 2026-11-06.

PayamBridge source is a new implementation licensed MIT. Official documentation is used as protocol evidence, not redistributed as an SDK implementation. Selected short method excerpts and their original URLs/hashes are retained under `snapshots/` for contract review. Public documentation tokens are not used as credentials.

R1 (`rubika-bot` 1.0.19) was downloaded as a wheel and inspected without installation or execution. Its included LICENSE is MIT, copyright 2022 Ali RajabNezhad. Only the envelope and alternate getMe response shape were used as behavioral evidence. No Python source was copied or made a runtime dependency. Its upload code disables TLS verification; PayamBridge does not use that behavior. Its older API hostname is also not used.

No personal-account library has been adopted. S18/19/22/23/24 are research candidates, not approved dependencies. In particular, the specification's GPL/proprietary observations must be checked against exact commits before reuse.

`dependency-licenses.json` records exact versions and license metadata from the development lock, separately for runtime and development dependencies. Composer's installed license files and upstream distribution terms remain authoritative. The consumer's resolved versions may differ because this is a library. There is no Composer plugin or install hook in either PayamBridge package.
