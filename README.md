[![CC BY 4.0][cc-by-shield]][cc-by]

# Dissertation Resources

## Contents

This repository contains a range of data that can be used in combination with the Corpus of Spontaneous Japanese (CSJ) and the Corpus of Workplace Conversation (CWPC) provided by the National Institute for Japanese Language and Linguistics (NINJAL) to obtain the same dataset and outputs as presented within my dissertation.

The R Scripts folder contains the code used to generate the two models (CSJ Model.R, CWPC Model.R), as well as scripts that were used to evaluate whether a random-effect or fixed-effect model should be used (CSJ Model Comparison.R, CWPC Model Comparison.R).

The PHP Scripts folder contains the code used to re-structure and re-level the information from the CWPC/CSJ datasets in order to be used in the model. This is to encourage reproducibility, as these scripts could be run on new extracted datasets from these corpora and would result in the same structure as I used.

The questions used in the experiment, as well as the responses received, are all stored within the file 'Question List and Results.xlsx'.
---

## Accessing the datasets

Due to the nature of the corpora used and the copyright restrictions that have been put upon them, the datasets used in my dissertation cannot be made Open Access. As such, you will need to gain access to the corpora in order to make use of the queries and models within this repository.

The CSJ corpus is available on a paid basis from the National Institute for Japanese Language and Linguistics (NINJAL).

The CWPC corpus is available for free via the Chūnagon platform; however, user registration is required.

---

## Licensing

The works in this repository are licensed under a [Creative Commons Attribution 4.0 International License][cc-by]. This means that you are free to share, remix, build upon, and adjust the queries and code in this repository, as long as you give attribution to the work in which it was originally used, i.e. my dissertation.

[cc-by]: http://creativecommons.org/licenses/by/4.0/
[cc-by-shield]: https://img.shields.io/badge/License-CC%20BY%204.0-lightgrey.svg

---

## Acknowledgements

With great thanks to the [National Institute for Japanese Language and Linguistics](https://www.ninjal.ac.jp/english/), the creators of the CWPC and CSJ corpora.

---

## References

> NINJAL (2003) The Corpus of Spontaneous Japanese. Available at: https://clrd.ninjal.ac.jp/csj/en/index.html (Accessed on: 27 August 2022)

> NINJAL (2018) 現日研・職場談話コーパス Gen-Nichi-Ken / shokuba danwa cōpasu. Available in Japanese at: https://clrd.ninjal.ac.jp/csj/en/index.html (Accessed on: 27 August 2022)
