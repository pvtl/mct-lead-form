# A WordPress plugin for MCT for embedding lead forms.

## Installation

#### 1. Go to project root folder
Simply run `cd /projects/{project name}`

#### 2.
Simply run `composer require "pvtl/mct-lead-form": "^1.1"`.

#### 3. copy & rename .env file
Simply run `cp .env.example .env`

#### 4. Edit .env file and paste code below, replace values
```
# MCT Lead Form
MCT_API_HOST='{API HOST URL}'
MCT_API_TOKEN='{API TOKEN}'
MCT_API_BUSINESS_ID={API BUSINESS ID}
MCT_LEAD_FORM_REDIRECT='{WP SLUG THANK YOU PAGE}' ///valuation-thank-you/
```
#### 5. Edit .env file and paste code below, replace values (@sinc 1.3.7)
5.1 Run `cd /projects/{project name}/web/app`
5.2 Run `mkdir mct-lead-form-logs` to create new folder for mct logs

---

## Form templates can be overriden in the theme level
You can copy templates folder and paste it into `themes/{theme name}/mct/`. If `mct` folder is missing you need to create one.

---

## Multistep Form
You can copy multisteps form implementation from `https://github.com/pvtl/buyyourcar.git`.

**Important Notes:**
If ever you are implementing multistep form you need to include the script to the main script.
Or if you are having issue with mainmultistep.js file not updating, kindly copy gulp.js line 120 - 139 adjust the code accordingly.

---

## Upgrading

#### 1. Push your updates

#### 2. Update version number
Update the version number of this plugin from `mct-lead-form.php`.

#### 3. Create version tag
Create a new tag here `https://github.com/pvtl/mct-lead-form/releases/new`.

#### 4. Update version number from the wordpress site
The `composer.json` should use the new version number of this plugin.
Run `composer update pvtl/mct-lead-form` then push your composer lock file.