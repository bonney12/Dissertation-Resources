library(Boruta)
library(car)
library(effects)
library(emmeans)
library(flextable)
library(ggfortify)
library(ggplot2)
library(ggpubr)
library(Hmisc)
library(knitr)
library(lme4)
library(MASS)
library(mclogit)
library(MuMIn)
library(nlme)
library(ordinal)
library(rms)
library(robustbase)
library(sjPlot)
library(stringr)
library(tibble)
library(vcd)
library(vip)
library(dplyr)
library(forcats)
library(parallel)
library(doParallel)
library(likert)

options(scipen=999)
options(na.action = "na.fail")

# Load the dataset
dataset <- read.csv('dataset/CWPC.csv', stringsAsFactors = FALSE, encoding = 'UTF-8')


# Get a subset of the data that only includes the predictor variables we are hoping to use.
dataset <- dataset %>%
  dplyr::select(SpeakerID, IsHumble, Affected, Speaker_Sex, Speaker_AgeRange, Speaker_Occupation_MinorCategory, Interlocutor_Sex, Interlocutor_AgeRange, Interlocutor_Occupation_MinorCategory)

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
    Speaker_Occupation_MinorCategory = factor(Speaker_Occupation_MinorCategory, ordered = TRUE),
    Interlocutor_Occupation_MinorCategory = factor(Interlocutor_Occupation_MinorCategory, ordered = TRUE)
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

# set contrasts
options(contrasts  =c("contr.treatment", "contr.poly"))
# create distance matrix
dataset.dist <- datadist(dataset)
# include distance matrix in options
options(datadist = "dataset.dist")

m0.glm = glm(IsHumble ~ 1, family = binomial, data = dataset)
m0.glmer = glmer(IsHumble ~ Affected + Speaker_Sex + Speaker_AgeRange + Interlocutor_Sex + Interlocutor_AgeRange + Speaker_Occupation_MinorCategory + Interlocutor_Occupation_MinorCategory + (1 | SpeakerID), family = binomial, data = dataset)

#drg <- dredge(m0.glmer)
#model.avg(drg, subset = cumsum(weight) <= .95)
#bestModel <- summary(get.models(drg, 1)[[1]])

bestModel <- glmer(IsHumble ~ Affected + Interlocutor_AgeRange + Speaker_Occupation_MinorCategory + Speaker_Sex + (1 | SpeakerID), family = binomial, data = dataset)

models.glm <- bestModel  # rename final minimal adequate glm model

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
    y = "Actual response"
  )

confusionMatrixPlot

ggsave(
  filename = 'CWPC_GLMER_Confusion_Matrix.png',
  device = 'png',
  width = 1000,
  height = 1000,
  units = 'px',
  dpi = 300
)

predict.accuracy <- predict.acc[3]$overall[[1]]
