$(document).ready(function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Image preview before upload
    $('.custom-file-input').on('change', function() {
        const files = Array.from(this.files);
        const previewContainer = $(this).closest('.form-group').find('.image-preview');
        previewContainer.empty();

        files.forEach(file => {
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewContainer.append(`
                        <div class="position-relative d-inline-block me-2 mb-2">
                            <img src="${e.target.result}" class="img-thumbnail" style="height: 100px;">
                            <button type="button" class="btn-close position-absolute top-0 end-0 m-1" 
                                    style="background-color: white;"></button>
                        </div>
                    `);
                }
                reader.readAsDataURL(file);
            }
        });
    });

    // Remove preview image
    $(document).on('click', '.image-preview .btn-close', function() {
        $(this).closest('.position-relative').remove();
    });

    // Toggle favorite
    $('.btn-favorite').on('click', function(e) {
        e.preventDefault();
        const btn = $(this);
        const listingId = btn.data('listing-id');

        $.post('/ajax/toggle_favorite.php', { listing_id: listingId })
            .done(function(response) {
                if (response.success) {
                    btn.toggleClass('active');
                    const icon = btn.find('i');
                    const textSpan = btn.find('.favorite-text');
                    const countSpan = btn.find('.favorite-count');
                    const currentCount = parseInt(countSpan.text().replace(/[()]/g, '')) || 0;
                    
                    if (btn.hasClass('active')) {
                        icon.removeClass('far').addClass('fas');
                        textSpan.text("Remove from favorites");
                        countSpan.text(`(${currentCount + 1})`);
                    } else {
                        icon.removeClass('fas').addClass('far');
                        textSpan.text("Add to favorites");
                        countSpan.text(`(${Math.max(0, currentCount - 1)})`);
                    }
                }
            })
            .fail(function() {
                alert('Error toggling favorite. Please try again.');
            });
    });

    // Message polling
    let messagePolling;
    if ($('.message-thread').length) {
        const threadId = $('.message-thread').data('thread-id');
        
        function pollMessages() {
            $.get('/ajax/get_messages.php', { thread_id: threadId, last_id: getLastMessageId() })
                .done(function(response) {
                    if (response.messages && response.messages.length) {
                        appendNewMessages(response.messages);
                        scrollToBottom();
                    }
                });
        }

        function getLastMessageId() {
            const lastMessage = $('.message-bubble').last();
            return lastMessage.length ? lastMessage.data('message-id') : 0;
        }

        function appendNewMessages(messages) {
            messages.forEach(message => {
                $('.message-thread').append(`
                    <div class="message-bubble ${message.is_mine ? 'message-sent' : 'message-received'}"
                         data-message-id="${message.id}">
                        ${message.text}
                        <small class="d-block mt-1 text-muted">
                            ${message.timestamp}
                        </small>
                    </div>
                `);
            });
        }

        function scrollToBottom() {
            const thread = $('.message-thread');
            thread.scrollTop(thread[0].scrollHeight);
        }

        // Start polling
        messagePolling = setInterval(pollMessages, 5000);
        scrollToBottom();
    }

    // Send message
    $('#message-form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const input = form.find('textarea');
        const text = input.val().trim();

        if (text) {
            $.post('/ajax/send_message.php', form.serialize())
                .done(function(response) {
                    if (response.success) {
                        input.val('');
                        appendNewMessages([{
                            id: response.message_id,
                            text: text,
                            is_mine: true,
                            timestamp: 'Just now'
                        }]);
                        scrollToBottom();
                    }
                })
                .fail(function() {
                    alert('Error sending message. Please try again.');
                });
        }
    });

    // Rating stars
    $('.rating-input').on('change', function() {
        const value = $(this).val();
        const stars = $(this).closest('.rating-stars').find('.fa-star');
        
        stars.each(function(index) {
            if (index < value) {
                $(this).removeClass('far').addClass('fas');
            } else {
                $(this).removeClass('fas').addClass('far');
            }
        });
    });

    // Clean up on page unload
    $(window).on('unload', function() {
        if (messagePolling) {
            clearInterval(messagePolling);
        }
    });

    // Image gallery
    $('.gallery-thumbnail').on('click', function() {
        const mainImage = $('#main-image');
        const newSrc = $(this).attr('src');
        const oldSrc = mainImage.attr('src');
        
        mainImage.fadeOut(200, function() {
            $(this).attr('src', newSrc).fadeIn(200);
        });
        
        $(this).attr('src', oldSrc);
    });

    // Form validation
    $('form[data-validate]').on('submit', function(e) {
        const form = $(this);
        if (!form[0].checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        }
        form.addClass('was-validated');
    });
}); 