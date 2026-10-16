# Model selection behavior

The analysis request accepts `both`, `sdxl_detector`, or `community_forensics`. The browser defaults to `both`. Responses include an entry for each model; unselected models have status `not_selected`, absent weights report `unavailable`, runtime failures report `error`, and completed inference reports `success`.

Each model result has its own model version, prediction, AI/human scores, and inference time. The top-level legacy `predictions` array remains populated from the successful SDXL result when available, otherwise from Community Forensics. Existing history receives one aggregate row per image, while the migration-backed model history stores one row per selected model attempt.
