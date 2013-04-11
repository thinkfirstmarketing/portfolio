
#this is a class that extends a base Dialog that is used all over the website that we were working on. This particular handler handles the update information box that the company can use to change informatoin about the user. It's written in coffeescript (which, if you're not familiar compiles into coffeescript. It's just some syntactic sugar on top of the very parenthesis-y JS.) 

class Portal.Edit_User_Dialog extends Dialog_Handler
    init: ()->
        @layoutUrl = 'users/dialog' #shows where to get the base layout for the dialog
        @saveUrl = 'users/update' #shows what action to call when the dialog information is saved 
        @createDialog()

    afterLoadLayout: (selector, data)->
        ths = this #syntactic sugar to help access this dialog when inside of post requests or others

        #shows the visuals of the dialog on the page 
        @openDialog()

        #enables validation on the page
        #we had a custom validation engine written for this particular project
        setUpValidation
            '#frm_edit_user':()=>
                @save('Saving...')

        #custom rule for this element 
        registerValidationRule 'validate-required-if-full', "Email is required for Full access accounts", (elem)->
            return parseInt(ths.saveData['level_id']) != parseInt(data.data['full_id']) || $(elem).val() != ''

        #bind the save action to the form, and then validate before submitting
        @dialog.find('.save-btn').bind 'click',(e) =>
            mfValidate()

        #only submits if the user is allowed to change an access level on the page. 
        @dialog.find('.access-levels-container button').bind 'click',(e)->
            return false if $(this).hasClass('disabled')
            ths.saveData['level_id'] = $(this).data('level-id')
            mfValidateInput(ths.dialog.find('input[name="email_address"]'))
            return true 

        #instantiates a new dialog for handling the resetting of passwords. 
        @dialog.find('#reset-password-btn').bind 'click',(e)=>
            handler = @createSubHandler('Portal.Confirm_Reset_Password_Dialog')
            if handler
                handler.loadLayoutPostOptions =
                    'users_id': $('input[name="user_id"]').val()
                handler.init()

        #
        @dialog.find('button[data-toggle="button"]').button('toggle').trigger('click')
