# DVC Example Data Organization & Import Strategy

This document provides an analysis of the various DVC data file structures found in the `example-data` folder, outlining how to approach importing, analyzing, and visualizing them.

## 1. File Structures Overview

We have 4 distinct CSV structures representing DVC telemetry data:

### `EMF_data_edge.csv` (Wide Format - Raw Electrodes)
- **Time Columns**: `timestamp` (ISO with offset), `day`, `hour`, `minute`, `relativeTime`
- **Identifiers**: `group`, `cage`
- **Data Columns**: `samples`, `v_1` through `v_12` (representing the 12 electrodes under the cage).
- **Import Handling**: Requires aggregating `v_1` to `v_12` (by summing or averaging) to get a total cage activity metric, as it provides raw electrode data.

### `data-example_edge_10s.csv` (Wide Format - Pre-calculated Activity)
- **Time Columns**: `timestamp` (ISO with offset)
- **Identifiers**: `group`, `cage`
- **Data Columns**: `samples`, `activity`
- **Import Handling**: This is the cleanest format. The 12 electrodes have already been aggregated into a single `activity` metric at 10-second intervals. 

### `dvc_processed_data.csv` (Wide Format - Electrodes + Global)
- **Time Columns**: `timestamp`
- **Identifiers**: Missing explicit `group` and `cage` identifiers in the first few rows (likely represents a single cage or aggregate).
- **Data Columns**: `e1` through `e12`, `Global_Activity`
- **Import Handling**: Similar to EMF data, but uses `e1`-`e12` instead of `v_1`-`v_12`. It already provides a `Global_Activity` column, so manual aggregation of `e1-e12` is optional depending on whether spatial analysis is needed.

### `small_All_Metrics_60s_Edge_Activ_Raw.csv` (Long Format - Melted)
- **Time Columns**: `dt_start` (timestamp), `day`, `month`, `year`
- **Identifiers**: `cage_uuid`, `cage_name`, `cage_position` (No explicit `group`)
- **Data Columns**: `metric` (e.g., `activation-index`), `value`, `resampling`, `modifier`
- **Import Handling**: This uses a "long" format. The importer needs to pivot this data so that the `metric` string becomes a column header and `value` becomes the row data, aligned by `dt_start` and `cage_name`.

---

## 2. Smart Importer Strategy

To support these varied formats seamlessly, the `LocalDataUploader` should utilize a "Smart Import" strategy:

1. **Timestamp Normalization**: Detect `timestamp` or `dt_start`. Parse timezone offsets correctly to ensure circadian rhythms are aligned correctly.
2. **Identifier Extraction**: Look for `cage`, `cage_name`, or `cage_uuid` to define subjects. Look for `group` to define experimental conditions. 
3. **Format Detection & restructuring**:
   * **Long Format Detection**: If columns `metric` and `value` exist, pivot the dataset automatically mapping `metric` to columns.
   * **Electrode Aggregation**: If columns `v_1` to `v_12` or `e1` to `e12` exist, and no overall activity column is found, automatically calculate an `Average_Activity` and `Total_Activity` column.
   * **Direct Metrics**: Read established metrics like `activity` or `Global_Activity`.

---

## 3. Recommended Data Analysis & Visualization Modules

Given the nature of continuous home-cage monitoring via DVC, the following capabilities are required for robust scientific analysis:

### A. Data Processing & Statistics
- **Binning/Downsampling**: The ability to dynamically downsample high-frequency data (e.g., 10s to 1h bins) using sums or averages for longitudinal analysis.
- **Statistical Significance**: Modules for Repeated Measures ANOVA or Linear Mixed-Effects Models (LMM) to validate statistical differences between `groups` over time.

### B. Dynamic Visualizations
- **Raster Plots / Actograms**: Double-plotted actograms to visualize 24hr circadian rhythms and sleep/wake cycles for individual cages. *(Already partially implemented)*
- **Group Activity Profiles**: Line or Area charts displaying `mean ± SEM` (Standard Error of the Mean) across groups over 24 hours or multiple days. *(Implemented)*
- **Spatial Heatmaps**: For datasets maintaining `v_1-v_12` or `e1-e12`, plot a 3x4 grid heatmap to show spatial preference (nest building vs. feeding behavior) within the cage over a specific time window.
- **Sleep Analysis**: Dynamic thresholding algorithms to classify sustained periods of low activity (e.g., < 5% threshold for > 40 seconds) as putative sleep bouts.
