-- Backward-compatible per-model history. Existing analysis_results remains
-- the one-row-per-upload table used by the analytics dashboard.
CREATE TABLE IF NOT EXISTS model_analysis_results (
    result_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    analysis_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    model_id VARCHAR(32) NOT NULL,
    model_version VARCHAR(128) NOT NULL,
    prediction VARCHAR(16) NULL,
    ai_score DOUBLE NULL,
    human_score DOUBLE NULL,
    inference_time_ms INT UNSIGNED NULL,
    status VARCHAR(16) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (result_id),
    UNIQUE KEY uq_analysis_model (analysis_id, model_id),
    KEY ix_model_created (model_id, created_at),
    KEY ix_analysis_id (analysis_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
