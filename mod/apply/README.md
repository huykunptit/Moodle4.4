# mod_apply

## Install
```
git clone https://github.com/moodle-fumihax/mod_apply.git apply
chown -R apache.apache apply
```
## OverView
* This module is for submission of the application form (for Moodle 2.4).
* This module has been created by modifying the feedback module of Andreas Grabs created.
* This application form module, you will be able to create a simple application form and to submit it to the user.
* Teacher checks the submitted application and processes it as "accepted" or "reject".
* On the other hand user can "update" of the submitted application form, and "withdrawn", to perform the "cancellation".

## The difference with the Feedback module
* You can not post as anonymous (guest).
* You can post more than one.
* Teacher can perform any actions on the application form was submitted.  
    Processing content: accept/reject, notification of execution, add to comment.
* During the creation of the application documents, you can use special label that have a special role to each item.
* Template functions are not implemented, yet.

## Special Labels
* By specifying the following reserved words as the label of an item, it is possible to assign a special role to an item.
### submit_title
 When this label is attached to the textfield (Short text answer), it is treated as a title of an application.
### submit_only
 This is an item displayed only at the time of an application. This is used for use consent etc.
### admin_reply
 Although not displayed on a user at the time of an application, it is displayed after an application. 
 Since the administrator can edit, This is uses for the comment from an administrator, etc.
### admin_only
 This is an item which can be displayed to only an administrator and can be edited by only an administrator.
 It is used for an administrator's memo etc.

 
