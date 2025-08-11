# WT AmoCRM - RadicalForm for Joomla
Plugin for sending data to amoCRM from Joomla feedback forms created using the professional Radical Form plugin. 
- [More info on developer site](https://web-tolk.ru/dev/joomla-plugins/wt-amocrm-radicalform.html)
- Radical Form joomla callback plugin. Download and docs on [Site](https://hika.su/rasshireniya/radical-form) [GitHub](https://github.com/Delo-Design/radicalform)
<img width="1359" height="767" alt="image" src="https://github.com/user-attachments/assets/ee4144aa-fc62-41fa-9ecc-c58ebec909b3" />

## Plugin features
- creating leads in Amo CRM
- choosing a sales pipeline to create leads on
- ability to specify the lead tag when creating
- the ability to specify the name of the lead - the field rfSubject
- the ability to specify a pipeline for each form - the pipeline_id field in the form
- the ability to specify for each form its own form_id.
- form fields named phone and email are defined as phone and email by default
- definition of all 18 types of UTM tags that are possible in Amo CRM. A js script is added to the site pages, which saves all detected UTM tags into session cookies. If the form is sent after some time or from other pages, UTM tags will still be specified in the lead.
## WT Amo CRM library
Require WT Amo CRM library:
- [Joomla package installation on developer site](https://web-tolk.ru/dev/biblioteki/wt-amo-crm-library.html)
- [See library code on Github](https://github.com/sergeytolkachyov/WT-Amo-CRM-library-for-Joomla-4)
