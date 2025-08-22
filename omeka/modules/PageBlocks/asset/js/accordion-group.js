$(document).ready(function () {
    const sidebar = $('#accordion-sidebar');
    var selectingElement;
    
    $('#content').on('click', '.accordion-form-add, .accordion-form-edit', function () {
        selectingElement = $(this);
        
        if (!selectingElement.hasClass('accordion-form-add')) {
            sidebar.find('input, textarea').each(function (index, elem) {
                elem = $(elem);
                
                const attachment = selectingElement.parents('.attachment');
                elem.val(
                    attachment.find('input[data-sidebar-id="' + elem.attr('data-sidebar-id') + '"]').val());
            });
        } else {
            sidebar.find('input, textarea').val('');
        }
        
        Omeka.openSidebar(sidebar);
    });
    
    $('#content').on('click', '#accordion-sidebar .confirm-panel > button', function () {
        Omeka.closeSidebar(sidebar);
        if (selectingElement.hasClass('accordion-form-add')) {
            const attachments = selectingElement.parents('.attachments');
            const newAttachment = $(attachments.data('template'));
            attachments.append(newAttachment);
            selectingElement = newAttachment.find('.accordion-form-edit');
        }
        
        // const html = $(sidebar).find(".wysiwyg").data('ckeditorInstance').getData();
        
        $(this).parents('.sidebar').find('input, textarea').each(function (index, elem) {
            elem = $(elem);
            
            const attachment = selectingElement.parents('.attachment');
            attachment.find('input[data-sidebar-id="' + elem.attr('data-sidebar-id') + '"]')
                .val(elem.val());
                
            if (elem.attr("data-sidebar-id") == "accordion-data-title") {
                attachment.find('.asset-title').text(elem.val());
            }
        });
    });
});