$(document).ready(function () {
    const sidebar = $('#member-sidebar');
    var selectingElement;
    
    $('#content').on('click', '.member-form-add, .member-form-edit', function () {
        selectingElement = $(this);
        
        if (!selectingElement.hasClass('member-form-add')) {
            sidebar.find('input').each(function (index, elem) {
                elem = $(elem);
                
                const attachment = selectingElement.parents('.attachment');
                elem.val(
                    attachment.find('input[data-sidebar-id="' + elem.attr('data-sidebar-id') + '"]').val());
            });
        } else {
            sidebar.find('input').val('');
        }
        
        Omeka.openSidebar(sidebar);
    });
    
    $('#content').on('click', '#member-sidebar .confirm-panel > button', function () {
        Omeka.closeSidebar(sidebar);
        if (selectingElement.hasClass('member-form-add')) {
            const attachments = selectingElement.parents('.attachments');
            const newAttachment = $(attachments.data('template'));
            attachments.append(newAttachment);
            selectingElement = newAttachment.find('.member-form-edit');
        }
        
        $(this).parents('.sidebar').find('input').each(function (index, elem) {
            elem = $(elem);
            
            const attachment = selectingElement.parents('.attachment');
            attachment.find('input[data-sidebar-id="' + elem.attr('data-sidebar-id') + '"]')
                .val(elem.val());
                
            if (elem.attr("data-sidebar-id") == "member-data-name") {
                attachment.find('.asset-title').text(elem.val());
            }
        });
    });
});