library(MASS)
library(tidyverse)
library(dplyr)
library(lme4)
library(rms)
library(caret)
library(sjPlot)
library(MuMIn)
library(ggfortify)
library(ggplot2)
library(grid)
library(gridExtra)
library(likert)

# Prevent scientific notation of numbers
options(scipen=999)
options(na.action = "na.fail")

# Load the dataset
dataset <- read.csv('dataset/CSJ.csv', stringsAsFactors = FALSE, encoding = 'UTF-8')

# Get a subset of the data that only includes the predictor variables we are hoping to use.
dataset <- dataset %>%
  select(SpeakerID, IsHumble, Affected, Speaker_Sex, Speaker_AgeRange, Interlocutor_Sex, Interlocutor_AgeRange)

# Adjust variables as needed
dataset <- dataset %>%
  dplyr::mutate(
    SpeakerID = factor(SpeakerID),
    Affected = fct_explicit_na(factor(Affected), na_level = 'NONE'),
    Speaker_Sex = factor(Speaker_Sex),
    Speaker_AgeRange = factor(Speaker_AgeRange),
    Interlocutor_Sex = factor(Interlocutor_Sex),
    Interlocutor_AgeRange = factor(Interlocutor_AgeRange)
  )

# Relevel data
dataset <- dataset %>%
  dplyr::mutate(
    Affected = relevel(Affected, 'NONE'),
    Speaker_Sex = relevel(Speaker_Sex, 'M'),
    Speaker_AgeRange = relevel(Speaker_AgeRange, '60-69'),
    Interlocutor_Sex = relevel(Interlocutor_Sex, 'M'),
    Interlocutor_AgeRange = relevel(Interlocutor_AgeRange, '60-69')
  )

# Order data by SpeakerID
dataset <- dataset %>%
  dplyr::arrange(SpeakerID)

# set contrasts
options(contrasts  =c("contr.treatment", "contr.poly"))
# create distance matrix
dataset.dist <- datadist(dataset)
# include distance matrix in options
options(datadist = "dataset.dist")

# Create baseline model
m0.blr = glm(IsHumble ~ 1, family = binomial, data = dataset)

# Find best model automatically
full.blr <- glm(IsHumble ~ Affected + Speaker_Sex + Speaker_AgeRange + Interlocutor_Sex + Interlocutor_AgeRange, family = binomial, data = dataset)
drg <- dredge(full.blr)

# Take models that have a delta of below 5
subset(drg, delta < 5)

#model.avg(drg, subset = cumsum(weight) <= .95)

#bestModel <- summary(get.models(drg, 1)[[1]]) <- This returns IsHumble ~ Affected + 1

bestModel = glm(formula = IsHumble ~ Affected + 1, family = binomial, data = dataset)

bestModel.lrm <- lrm(IsHumble ~ Affected + 1, data = dataset, x = T, y = T, linear.predictors = T)

models.glm <- bestModel  # rename final minimal adequate glm model
models.lrm <- bestModel.lrm  # rename final minimal adequate lrm model

modelChi <- models.glm$null.deviance - models.glm$deviance
chidf <- models.glm$df.null - models.glm$df.residual
chisq.prob <- 1 - pchisq(modelChi, chidf)
modelChi; chidf; chisq.prob

ncases <- length(fitted(models.glm))
R2.hl <- modelChi/models.glm$null.deviance
R.cs <- 1 - exp ((models.glm$deviance - models.glm$null.deviance)/ncases)
R.n <- R.cs /( 1- ( exp (-(models.glm$null.deviance/ ncases))))

# function for extracting pseudo-R^2
logisticPseudoR2s <- function(LogModel) {
  dev <- LogModel$deviance
  nullDev <- LogModel$null.deviance
  modelN <-  length(LogModel$fitted.values)
  R.l <-  1 -  dev / nullDev
  R.cs <- 1- exp ( -(nullDev - dev) / modelN)
  R.n <- R.cs / ( 1 - ( exp (-(nullDev / modelN))))
  cat("Pseudo R^2 for logistic regression\n")
  cat("Hosmer and Lemeshow R^2  ", round(R.l, 3), "\n")
  cat("Cox and Snell R^2        ", round(R.cs, 3), "\n")
  cat("Nagelkerke R^2           ", round(R.n, 3),    "\n") }

logisticPseudoR2s(models.glm)
confint(models.glm)
exp(models.glm$coefficients)
exp(confint(models.glm))


# create variable with contains the prediction of the model
dataset <- dataset %>%
  dplyr::mutate(Prediction = predict(models.glm, type = "response"),
                Predictions = ifelse(Prediction > .5, 1, 0),
                Predictions = factor(Predictions, levels = c("0", "1")),
                IsHumble = factor(IsHumble, levels = c("0", "1")))
# create a confusion matrix with compares observed against predicted values
predict.acc <- caret::confusionMatrix(dataset$Predictions, dataset$IsHumble)

# predicted probability
sjPlot::plot_model(models.glm, type = "pred", terms = c("Affected"), axis.lim = c(0, 1)) +
  theme(legend.position = "top") +
  labs(x = "", y = "Predicted Probabilty of IsHumble", title = "") +
  scale_color_manual(values = c("gray20", "gray70"))

infl <- influence.measures(models.glm) # calculate influence statistics
dataset <- data.frame(dataset, infl[[1]], infl[[2]]) # add influence statistics

# Check sample size is sufficient according to Green (1991)
smplesz <- function(x) {
  ifelse((length(x$fitted) < (104 + ncol(summary(x)$coefficients)-1)) == TRUE,
         return("Sample too small"),
         return("Sample size sufficient")) }

#smplesz(models.glm)

#sjPlot::tab_model(models.glm)

cm_d <- as.data.frame(predict.acc$table) # extract the confusion matrix values as data.frame
cm_st <-data.frame(predict.acc$overall) # confusion matrix statistics as data.frame
cm_st$cm.overall <- round(cm_st$predict.acc.overall,2) # round the values
cm_d$diag <- cm_d$Prediction == cm_d$Reference # Get the Diagonal
cm_d$ndiag <- cm_d$Prediction != cm_d$Reference # Off Diagonal     
cm_d$Reference <-  reverse.levels(cm_d$Reference) # diagonal starts at top left
cm_d$ref_freq <- cm_d$Freq * ifelse(is.na(cm_d$diag),-1,1)

confusionMatrixPlot <-  ggplot(data = cm_d, aes(x = Prediction , y =  Reference, fill = Freq))+
  scale_x_discrete(position = "top") +
  geom_tile( data = cm_d,aes(fill = ref_freq)) +
  scale_fill_gradient2(guide = FALSE ,low="red3",high="orchid4", midpoint = 0,na.value = 'white') +
  geom_text(aes(label = Freq), color = 'black', size = 4)+
  theme_bw() +
  theme(panel.grid.major = element_blank(), panel.grid.minor = element_blank(),
        legend.position = "none",
        panel.border = element_blank(),
        plot.background = element_blank(),
        axis.line = element_blank(),
        axis.text = element_text(family = "Econ Sans Cnd", size = 10),
        axis.title = element_text(family = "Econ Sans Cnd", size = 10),
        plot.title = element_text(family = "Econ Sans Cnd", size = 12),
        plot.subtitle = element_text(family = "Econ Sans Cnd", size = 10)
  ) +
  labs(
    x = "Predicted response",
    y = "Actual response",
    title = "Confusion Matrix (CWPC GLMER Model)"
  )

confusionMatrixPlot

ggsave(
  filename = 'CSJ_GLM_Confusion_Matrix.png',
  device = 'png',
  width = 1000,
  height = 1000,
  units = 'px',
  dpi = 300
  )

predict.accuracy <- predict.acc[3]$overall[[1]]

report <- report::report(models.glm)