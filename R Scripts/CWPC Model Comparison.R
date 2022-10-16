library(tidyverse)
library(dplyr)
library(mlogit)
library(lme4)
library(forcats)
library(Hmisc)
library(rms)
library(lmtest)

#options(scipen=999)
options(na.action = "na.fail")

# Load the dataset
dataset <- read.csv('dataset/CWPC.csv', stringsAsFactors = FALSE, encoding = 'UTF-8')


# Get a subset of the data that only includes the predictor variables we are hoping to use.
dataset <- dataset %>%
  select(SpeakerID, IsHumble, Affected, Speaker_Sex, Speaker_AgeRange, Speaker_Occupation_MinorCategory, Interlocutor_Sex, Interlocutor_AgeRange, Interlocutor_Occupation_MinorCategory)

dataset <- dataset %>%
  dplyr::mutate(
    # Relevel 99 to 74 as a minor category so that uncategorised is the last number
    Speaker_Occupation_MinorCategory = replace(Speaker_Occupation_MinorCategory, Speaker_Occupation_MinorCategory == 99, 74),
    Interlocutor_Occupation_MinorCategory = replace(Interlocutor_Occupation_MinorCategory, Interlocutor_Occupation_MinorCategory == 99, 74)
  )

# Adjust variables as needed
dataset <- dataset %>%
  dplyr::mutate(
    SpeakerID = factor(SpeakerID),
    Affected = fct_explicit_na(factor(Affected), na_level = 'NONE'),
    Speaker_Sex = factor(Speaker_Sex),
    Speaker_AgeRange = factor(Speaker_AgeRange),
    Interlocutor_Sex = factor(Interlocutor_Sex),
    Interlocutor_AgeRange = factor(Interlocutor_AgeRange),
    Speaker_Occupation_MinorCategory = ordered(Speaker_Occupation_MinorCategory),
    Interlocutor_Occupation_MinorCategory = ordered(Interlocutor_Occupation_MinorCategory)
  )

dataset <- dataset %>%
  dplyr::mutate(
    Affected = relevel(Affected, 'NONE'),
    Speaker_Sex = relevel(Speaker_Sex, 'M'),
    Speaker_AgeRange = relevel(Speaker_AgeRange, '60-69'),
    Interlocutor_Sex = relevel(Interlocutor_Sex, 'M'),
    Interlocutor_AgeRange = relevel(Interlocutor_AgeRange, '60-69')
  )

dataset <- dataset %>%
  dplyr::arrange(SpeakerID)

# Plotting
ggplot(dataset, aes(Interlocutor_Occupation_MinorCategory, IsHumble, color = Speaker_Sex)) +
  facet_wrap(Interlocutor_Sex ~ Interlocutor_AgeRange) +
  stat_summary(fun = mean, geom = "point") +
  stat_summary(fun.data = mean_cl_boot, geom = "errorbar", width = 0.2) +
  theme_set(theme_bw(base_size = 10)) +
  theme(legend.position = "top") +
  labs(x = "Speaker sex", y = "Observed probability that utterance is humble")
  #scale_colour_manual(values = c("gray20", "gray70"))

ggplot(dataset, aes(x = Speaker_Occupation_MinorCategory, y = Interlocutor_Occupation_MinorCategory)) +
  geom_point() +
  facet_wrap(~ IsHumble)

# Model options
options(contrasts = c("contr.treatment", "contr.poly"))
dataset.dist <- datadist(dataset)
options(datadist = "dataset.dist")

# Generate a fixed-effect minimal baseline model
m0.glm = glm(IsHumble ~ 1, family = binomial, data = dataset)
# Generate a mixed-effect baseline model with SpeakerID for random effect
m0.glmer = glmer(IsHumble ~ (1 | SpeakerID), family = binomial, data = dataset)

# Check if random effect is permitted (compare AIC from m0.glm to AIC from m0.glmer)
# If glmer's AIC is smaller than AIC of glm, then inclusion of random intercept is justified.
aic.glmer <- AIC(logLik(m0.glmer))
aic.glm <- AIC(logLik(m0.glm))

print(paste("AIC of GLMER:", aic.glmer))
print(paste("AIC of GLM:", aic.glm))

aic.diff <- abs(aic.glmer-aic.glm)
print(paste("Diff. in AIC values:", aic.diff))

# Model Likelihood Ratio Test
null.id = (-2 * logLik(m0.glm)) + (2 * logLik(m0.glmer))
pvalue = pchisq(as.numeric(null.id), df = 1, lower.tail = F)

print(paste("P-value of likelihood test:", pvalue))