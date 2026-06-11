# Demo Path: Animal Study Command

1. Project Wizard
   - Open /projects/new and walk through details → operator assignment.
   - Create the project and land on the project dashboard.

2. Assign Animals
   - Open Assign animals from the project dashboard.
   - Multi-select Tick@Lab animals and attach to the project.

3. Animal Detail Page
   - Click an animal in the roster to open Overview, Weight, Timeline tabs.
   - Highlight housing, cohort, and latest status cards.

4. Live Weight Feed
   - Use Start live feed and Push weight to simulate scale updates.
   - Show the out-of-range badge when weights cross thresholds.

5. Treatment Event
   - Log a drug/pathogen treatment with lot, dose, operator, and time.
   - Confirm it appears in the Timeline.

6. Blood Sampling
   - Create a blood sample and show generated sample_id.
   - Verify the sample links back to the project.

7. Analysis Results
   - Review Flow, PCR, and Antibody tables.
   - Point out mini trend charts per marker.

8. Behavior Series
   - Review activity time series with circadian peaks.
   - Highlight anomaly callouts.

9. Animal 360 Timeline
   - Open the Timeline tab and show merged events in order.
   - Call out weights, treatments, samples, and analysis entries.

10. Project Dashboard
   - Show animals at risk, group weight evolution, and marker trends.
   - Review recent actions to close the loop.

# Demo Path: Industrial API Warfare (Level 5)

1. Operations Overview
   - Select a facility and review KPIs (active alarms, out-of-range cages, tasks due, sensors offline).
   - Walk the “Today” panel for upcoming checks and recent incidents.
   - Use quick actions to jump into Alarms, Map, and Conditions.

2. Facility Map (Drill-down)
   - Move from Facility → Room → Rack → Cage.
   - Toggle filters for alarm-only and sensor-offline views.
   - Open a cage detail view directly from the map.

3. Cage Detail
   - Review occupants and lifecycle.
   - Use the Timeline tab for audit/provenance events.
   - Open Conditions to show live snapshot + thresholds + trends.

4. Alarms Center
   - Filter by severity/scope and sort by created time.
   - Select multiple alarms for bulk acknowledge/resolve.
   - Open the detail panel and review readings context + correlation ID.

5. Conditions Dashboard
   - Switch scope type (room/rack/cage) and show live snapshots.
   - Change thresholds, save, and explain out-of-range signals.
   - Export readings to CSV.

6. Room Detail
   - Confirm tabs: Overview / Timeline / Conditions / Audit.
   - Show audit trail for room inspections.

7. Sensors & Subjects (Bonus)
   - Check sensor health and last-seen timestamps.
   - Open a subject record to verify cage assignment.

Optional: Use browser devtools to simulate offline mode and show MSW error handling.

# Demo Path: Feature Rush

1. Animal badge status
   - Open a project dashboard and point out OK/WARNING/CRITICAL badges in the roster.

2. Weight mini-sparkline
   - Show sparkline weight history next to each animal in the roster.

3. Quick note on animal
   - Open an animal detail and save a quick note in Overview.

4. Project tag system
   - Add a tag in the Tags card and show it appear immediately.

5. Favorite animals
   - Toggle the star on an animal in the roster; show it fill.

6. Recent activity widget
   - Open Projects list and review the Recent activity card.

7. Color-coded cages
   - Point out the colored cage dots in the roster rows.

8. Operator avatar on events
   - Open an animal timeline and show avatar initials per event.

9. CSV export button
   - Export the roster to CSV and confirm the last export timestamp updates.

10. Keyboard navigation
   - Focus the roster card, use ↑/↓ to move, Enter to open, and f to favorite.
