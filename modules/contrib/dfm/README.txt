Drupella File Manager
====================================
DFM is an ajax based file manager with drag drop user interface.


INSTALLATION
-----------
1. Copy dfm directory into your modules(sites/all/modules) directory.
2. Enable the module at /admin/modules.
3. Manage configuration profiles at /admin/config/media/dfm.
4. Access your files at /dfm

Note: If you are upgrading from DFM Lite just copy the full version over the old module folder.


CKEDITOR INTEGRATION
-----------
1. Go to /admin/config/content/formats to edit a text format that uses CKEditor.
2. Enable the image & link buttons provided by Dfm under Toolbar Configuration.
The buttons have the default image/link icons.


BUEDITOR INTEGRATION
-----------
1. Edit your editor at /admin/config/content/bueditor.
2. For image/link dialog integration: Select Drupella File Manager as the File browser under Settings.
3. You can also enable standalone image & link buttons to insert multiple images/links at once.


FILE/IMAGE FIELD INTEGRATION
-----------
1. Go to form settings of your content type. Ex: /admin/structure/types/manage/article/form-display
2. Edit widget settings of a file/image field.
3. Check the box saying "Allow users to select files from Drupella File Manager for this field." and save.
4. You should now see the "Select file" link above the upload widget in the content form.
