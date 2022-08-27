SELECT * FROM `CWPC Reformatted` WHERE `IsIgnored` IS NOT TRUE AND
                                       (
                                         `Occupation` = '*'
                                         OR
                                         `Interlocutor_Occupation` = '*'
                                         OR
                                        `AgeRange` = '?'
                                         OR
                                        `Interlocutor_AgeRange` = '?'
                                       )
                                 GROUP BY `TalkID`;
