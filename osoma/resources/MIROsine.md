MIROSine
The objective of MIROSine is to analyse the animals’ circadian rhythm of locomotor activity over the duration of the experiment. Simple periodicity with a defined period (24 hours in the case of a circadian rhythm) is well-described by the sinusoidal function, so a linear model with a sine and cosine term is fitted to the data. The given coefficients are used to calculate the amplitude, midline, and phase of a sine wave describing each sensor's recordings for every day of the experiment. These parameters can then be analysed across groups and time intervals, as will be done by MIRO The Explorer and StatistiSLAV afterwards. More information can be found in the MIROSLAV paper: insert DOI

If you are running MIROSine via Google Colab, MIROSine will autodetect and set up the Colab environment in the following cell, and pull example data from the MIROSLAV toolkit GitHub repository.

If you want to run MIROSine in Google Colab and with your own data, you can upload it using the File Browser in the sidebar on the left after running the following cell.

return_code <- suppressWarnings(system("pip list | grep -F google-colab"))
if (return_code == 0) {
is_colab = TRUE
} else {
is_colab = FALSE
}
if (is_colab) {
system("wget http://archive.ubuntu.com/ubuntu/pool/main/o/openssl/libssl1.1_1.1.1f-1ubuntu2.22_amd64.deb")
system("apt install ./libssl1.1_1.1.1f-1ubuntu2.22_amd64.deb")
system("rm ./libssl1.1_1.1.1f-1ubuntu2.22_amd64.deb")
options(
HTTPUserAgent =
sprintf(
"R/%s R (%s)",
getRversion(),
paste(getRversion(), R.version["platform"], R.version["arch"], R.version["os"])
)
)
install.packages("arrow", repos = "https://packagemanager.rstudio.com/all/__linux__/jammy/latest")
wd <- paste0(getwd(), "/")
dir.create(file.path(wd, "2_outputs_tidy"), showWarnings = FALSE)
system("wget -O 2_outputs_tidy/mph-pir-tidy-source1minute-resampled5minutes.parquet https://github.com/davorvr/MIROSLAV-analysis/raw/main/2_outputs_tidy/mph-pir-tidy-source1minute-resampled5minutes.parquet")
library(IRdisplay)
}

Requirements
First, we import the libraries we will require.

library(arrow)
library(dplyr)
library(lubridate)
library(progress)

Attaching package: 'dplyr'

The following objects are masked from 'package:stats':

    filter, lag

The following objects are masked from 'package:base':

    intersect, setdiff, setequal, union

Attaching package: 'lubridate'

The following objects are masked from 'package:base':

    date, intersect, setdiff, union

Import data
We import the data we will be working on. We need to define:

The name of the experiment we want to process,
The filename of the TidySLAV output,
The experiment's start and end times.

exp_name <- "mph"
tidydata_filename <- paste0(exp_name, "-pir-tidy-source1minute-resampled5minutes.parquet")
sinedata_filename <- paste0(exp_name, "\_sine_data.rds")
exp_start <- as_datetime("2022-05-07 05:46:00")
exp_end <- as_datetime("2022-05-31 17:46:00")

Then, we load TidySLAV data.

wd <- paste0(getwd(), "/")
data <- read_parquet(paste0(wd, "2_outputs_tidy/", tidydata_filename))
data
ts_recv, tz="UTC")
data <- data %>% filter(ts_recv >= as_datetime(exp_start, tz="UTC"))
data <- data %>% filter(ts_recv < as_datetime(exp_end, tz="UTC"))
data
ts_recv, min(data$ts_recv), units="hours")
data$hourcount <- as.numeric(data
n_day <- data$hourcount %/% 24
day_start_decimal <- 5+46/60
treatments <- unique(data$treatment)

Perform the calculations
We will now calculate the sine parameters for each sensor's respective daily recordings throughout the experiment.

sensor_animal_ids <- unique(data
n_day)
total_model_n <- length(sensor_animal_ids) \* length(days) # 3618
pb <- progress_bar$new(total = total_model_n)
sine_data <- data.frame(
n_day = rep(NA, total_model_n),
amplitude = rep(NA, total_model_n),
mesor = rep(NA, total_model_n),
phase = rep(NA, total_model_n),
peak_hour = rep(NA, total_model_n),
alpha = rep(NA, total_model_n),
beta = rep(NA, total_model_n),
#model=rep(NA, total_model_n),
sensor_id = rep(NA, total_model_n),
animal_id = rep(NA, total_model_n),
sensor_animal_id = rep(NA, total_model_n),
treatment = rep(NA, total_model_n),
date = rep(NA, total_model_n),
ts_start = rep(NA, total_model_n),
ts_end = rep(NA, total_model_n)
)
sine_models <- list()

i <- 0
pb <- progress_bar$new(total = total_model_n, force = is_colab)
for (id in sensor_animal_ids) {
  sine_models[[id]] <- list()
  for (day in days) {
    i <- i + 1
    data_1day <- data %>%
      filter(sensor_animal_id == id) %>%
      filter(n_day == day)
    n_day <- data_1day$n_day[1]

    sensor_id <- data_1day$sensor_id[1]
    animal_id <- data_1day$animal_id[1]
    sensor_animal_id <- data_1day$sensor_animal_id[1]
    date <- date(min(data_1day$ts_recv))
    ts_start <- min(data_1day$ts_recv)
    ts_end <- max(data_1day

treatment[1]

    model <- lm(miro_value ~ 1 +
                  sin(2 * pi * hourcount / 24) +
                  cos(2 * pi * hourcount / 24),
                data = data_1day)

    ### according to: https://stats.stackexchange.com/questions/77543/how-do-i-get-the-amplitude-and-phase-for-sine-wave-from-lm-summary
    intercept <- as.numeric(coef(model)["(Intercept)"])
    alpha <- as.numeric(coef(model)["sin(2 * pi * hourcount/24)"])
    beta <- as.numeric(coef(model)["cos(2 * pi * hourcount/24)"])

    # get parameters of the sine equation y = A*sin(x + P) + M; x = (2*pi/24)*t
    # where: A - amplitude, P - phase, M - mesor
    amplitude <- sqrt(alpha ^ 2 + beta ^ 2)
    mesor <- intercept
    phase <- atan2(beta, alpha)
    # since phase is in radians, we use it to instead calculate peak_hour, the
    # time of day of peak activity, as a biologically relevant parameter.
    # if there is no phase shift, a sine's first, positive amplitude occurs at one
    # quarter (pi/2) of the full sine period (2*pi). converting to hours, peak
    # activity (when phase=0) occurs at 6 hours from a day's start (05:46+6h = 11:46).
    # when we add the phase, if positive, it will offset the peak to the left (earlier),
    # and to the right (later) if negative as per the rules of transformation for
    # the sine function:
    # https://flexbooks.ck12.org/cbook/ck-12-precalculus-concepts-2.0/section/5.6/primary/lesson/phase-shift-of-sinusoidal-functions-pcalc/
    # so we convert the phase back from radians to hours, and subtract it from 6
    # to get the time of day of peak locomotor activity.
    peak_hour <- (day_start_decimal + 6) - (phase * (24 / (2 * pi)))
    if (peak_hour < 0) {
      peak_hour <- 24 + peak_hour
    } else if (peak_hour > 24) {
      peak_hour <- peak_hour - 24
    }

    sine_data[i, ] <- list(
      n_day = n_day,
      amplitude = amplitude,
      mesor = mesor,
      phase = phase,
      peak_hour = peak_hour,
      alpha = alpha,
      beta = beta,
      #model=model,
      sensor_id = sensor_id,
      animal_id = animal_id,
      sensor_animal_id = sensor_animal_id,
      treatment = treatment,
      date = date,
      ts_start = ts_start,
      ts_end = ts_end
    )
    sine_models[[sensor_animal_id]][[as.character(n_day)]] <- model

    if (is_colab) {
      clear_output()
    }
    pb$tick()

}
}

We will save two RDS files, one containing the results, and the other containing all of the models.

sine_data.file <- paste0(wd, "3_outputs_R/", exp_name, "\_sine_data.rds")
sine_models.file <- paste0(wd, "3_outputs_R/", exp_name, "\_sine_models.rds")
dir.create(file.path(wd, "3_outputs_R"), showWarnings = FALSE)
saveRDS(sine_data, sine_data.file)
saveRDS(sine_models, sine_models.file)
