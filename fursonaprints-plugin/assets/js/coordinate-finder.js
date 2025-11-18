/**
 * Coordinate Finder JavaScript
 * Handles interactive coordinate selection for mockup images
 */

(function($) {
    'use strict';

    let canvas, ctx;
    let mockupImage = null;
    let imageUrl = '';
    let points = [];
    let draggingPoint = null;
    let scale = 1;
    let offsetX = 0, offsetY = 0;

    const POINT_RADIUS = 8;
    const POINT_COLOR = '#ff4444';
    const POINT_HOVER_COLOR = '#ff0000';
    const LINE_COLOR = '#00aaff';
    const LINE_WIDTH = 2;

    $(document).ready(function() {
        canvas = document.getElementById('mockup-canvas');
        ctx = canvas ? canvas.getContext('2d') : null;

        initializeEventHandlers();
    });

    /**
     * Initialize all event handlers
     */
    function initializeEventHandlers() {
        // Upload mockup button
        $('#upload-mockup-btn').on('click', openMediaUploader);

        // Canvas interactions
        if (canvas) {
            $(canvas).on('click', handleCanvasClick);
            $(canvas).on('mousemove', handleCanvasMouseMove);
            $(canvas).on('mousedown', handleCanvasMouseDown);
            $(canvas).on('mouseup', handleCanvasMouseUp);
            $(canvas).on('mouseleave', handleCanvasMouseLeave);
        }

        // Action buttons
        $('#reset-points-btn').on('click', resetPoints);
        $('#save-coordinates-btn').on('click', saveCoordinates);

        // Mockup list actions
        $(document).on('click', '.edit-mockup', handleEditMockup);
        $(document).on('click', '.delete-mockup', handleDeleteMockup);
    }

    /**
     * Open WordPress media uploader
     */
    function openMediaUploader() {
        const mediaUploader = wp.media({
            title: 'Select Mockup Image',
            button: { text: 'Use this image' },
            multiple: false,
            library: { type: 'image' }
        });

        mediaUploader.on('select', function() {
            const attachment = mediaUploader.state().get('selection').first().toJSON();
            loadImage(attachment.url);
            imageUrl = attachment.url;
        });

        mediaUploader.open();
    }

    /**
     * Load and display image on canvas
     */
    function loadImage(url) {
        mockupImage = new Image();
        mockupImage.crossOrigin = 'Anonymous';

        mockupImage.onload = function() {
            // Calculate scale to fit canvas container
            const containerWidth = $('#canvas-container').width();
            const maxHeight = 600;

            scale = Math.min(
                containerWidth / mockupImage.width,
                maxHeight / mockupImage.height,
                1 // Don't scale up
            );

            canvas.width = mockupImage.width * scale;
            canvas.height = mockupImage.height * scale;

            $('#canvas-container').show();
            resetPoints();
            draw();
        };

        mockupImage.onerror = function() {
            showMessage('Failed to load image. Please try another image.', 'error');
        };

        mockupImage.src = url;
    }

    /**
     * Handle canvas click to place points
     */
    function handleCanvasClick(e) {
        if (!mockupImage || points.length >= 4) {
            return;
        }

        // Don't place point if we're dragging
        if (draggingPoint !== null) {
            return;
        }

        // Don't place point if CMD/Ctrl is held (drag mode)
        if (e.metaKey || e.ctrlKey) {
            return;
        }

        const rect = canvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;

        // Check if click is on canvas
        if (x >= 0 && x <= canvas.width && y >= 0 && y <= canvas.height) {
            points.push({ x, y });
            updateUI();
            draw();
        }
    }

    /**
     * Handle mouse down for dragging
     */
    function handleCanvasMouseDown(e) {
        if (!mockupImage || !(e.metaKey || e.ctrlKey)) {
            return;
        }

        const rect = canvas.getBoundingClientRect();
        const mouseX = e.clientX - rect.left;
        const mouseY = e.clientY - rect.top;

        // Check if clicking on a point
        for (let i = 0; i < points.length; i++) {
            const dist = Math.sqrt(
                Math.pow(points[i].x - mouseX, 2) +
                Math.pow(points[i].y - mouseY, 2)
            );

            if (dist <= POINT_RADIUS) {
                draggingPoint = i;
                canvas.style.cursor = 'grabbing';
                e.preventDefault();
                return;
            }
        }
    }

    /**
     * Handle mouse move for dragging and hover effects
     */
    function handleCanvasMouseMove(e) {
        if (!mockupImage) {
            return;
        }

        const rect = canvas.getBoundingClientRect();
        const mouseX = e.clientX - rect.left;
        const mouseY = e.clientY - rect.top;

        // Handle dragging
        if (draggingPoint !== null) {
            points[draggingPoint] = {
                x: Math.max(0, Math.min(canvas.width, mouseX)),
                y: Math.max(0, Math.min(canvas.height, mouseY))
            };
            draw();
            return;
        }

        // Handle hover cursor
        let hovering = false;
        if (e.metaKey || e.ctrlKey) {
            for (let i = 0; i < points.length; i++) {
                const dist = Math.sqrt(
                    Math.pow(points[i].x - mouseX, 2) +
                    Math.pow(points[i].y - mouseY, 2)
                );

                if (dist <= POINT_RADIUS) {
                    hovering = true;
                    break;
                }
            }
        }

        canvas.style.cursor = hovering ? 'grab' : (points.length < 4 ? 'crosshair' : 'default');
    }

    /**
     * Handle mouse up to stop dragging
     */
    function handleCanvasMouseUp(e) {
        if (draggingPoint !== null) {
            draggingPoint = null;
            canvas.style.cursor = 'grab';
        }
    }

    /**
     * Handle mouse leaving canvas
     */
    function handleCanvasMouseLeave(e) {
        if (draggingPoint !== null) {
            draggingPoint = null;
            canvas.style.cursor = 'default';
        }
    }

    /**
     * Draw mockup image, points, and lines
     */
    function draw() {
        if (!mockupImage || !ctx) {
            return;
        }

        // Clear canvas
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        // Draw image
        ctx.drawImage(mockupImage, 0, 0, canvas.width, canvas.height);

        // Draw lines between points
        if (points.length > 1) {
            ctx.strokeStyle = LINE_COLOR;
            ctx.lineWidth = LINE_WIDTH;
            ctx.beginPath();
            ctx.moveTo(points[0].x, points[0].y);
            for (let i = 1; i < points.length; i++) {
                ctx.lineTo(points[i].x, points[i].y);
            }
            if (points.length === 4) {
                ctx.closePath();
            }
            ctx.stroke();
        }

        // Draw points
        points.forEach((point, index) => {
            ctx.fillStyle = POINT_COLOR;
            ctx.beginPath();
            ctx.arc(point.x, point.y, POINT_RADIUS, 0, 2 * Math.PI);
            ctx.fill();

            // Draw point number
            ctx.fillStyle = '#ffffff';
            ctx.font = 'bold 12px Arial';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText((index + 1).toString(), point.x, point.y);
        });
    }

    /**
     * Reset all points
     */
    function resetPoints() {
        points = [];
        draggingPoint = null;
        updateUI();
        if (mockupImage) {
            draw();
        }
    }

    /**
     * Update UI state based on points
     */
    function updateUI() {
        $('#point-count').text(points.length);
        $('#reset-points-btn').prop('disabled', points.length === 0);
        $('#save-coordinates-btn').prop('disabled', points.length !== 4 || !$('#mockup-name').val().trim());
    }

    /**
     * Save coordinates via AJAX
     */
    function saveCoordinates() {
        const mockupName = $('#mockup-name').val().trim();

        if (!mockupName) {
            showMessage('Please enter a mockup name.', 'error');
            return;
        }

        if (points.length !== 4) {
            showMessage('Please define all 4 corner points.', 'error');
            return;
        }

        if (!imageUrl) {
            showMessage('Please upload an image.', 'error');
            return;
        }

        // Convert canvas coordinates back to image coordinates
        const imageCoordinates = points.map(p => ({
            x: p.x / scale,
            y: p.y / scale
        }));

        const data = {
            action: 'save_mockup_coordinates',
            nonce: fursonaPrintsCoords.nonce,
            mockup_name: mockupName,
            image_url: imageUrl,
            coordinates: JSON.stringify(imageCoordinates)
        };

        $('#save-coordinates-btn').prop('disabled', true).text('Saving...');

        $.post(fursonaPrintsCoords.ajax_url, data)
            .done(function(response) {
                if (response.success) {
                    showMessage(response.data.message, 'success');
                    // Reload page to update mockups list
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showMessage(response.data.message || 'Failed to save coordinates.', 'error');
                    $('#save-coordinates-btn').prop('disabled', false).text('Save Coordinates');
                }
            })
            .fail(function() {
                showMessage('Network error. Please try again.', 'error');
                $('#save-coordinates-btn').prop('disabled', false).text('Save Coordinates');
            });
    }

    /**
     * Handle edit mockup button click
     */
    function handleEditMockup(e) {
        e.preventDefault();
        const mockupId = $(this).data('mockup-id');

        $.post(fursonaPrintsCoords.ajax_url, {
            action: 'get_mockup_coordinates',
            nonce: fursonaPrintsCoords.nonce,
            mockup_id: mockupId
        })
        .done(function(response) {
            if (response.success) {
                $('#mockup-name').val(response.data.mockup_name);
                loadImage(response.data.image_url);
                imageUrl = response.data.image_url;

                // Convert image coordinates to canvas coordinates
                setTimeout(function() {
                    points = response.data.coordinates.map(p => ({
                        x: p.x * scale,
                        y: p.y * scale
                    }));
                    updateUI();
                    draw();
                }, 500);

                // Scroll to top
                $('html, body').animate({ scrollTop: 0 }, 500);
            } else {
                showMessage(response.data.message || 'Failed to load mockup.', 'error');
            }
        })
        .fail(function() {
            showMessage('Network error. Please try again.', 'error');
        });
    }

    /**
     * Handle delete mockup button click
     */
    function handleDeleteMockup(e) {
        e.preventDefault();

        if (!confirm('Are you sure you want to delete this mockup?')) {
            return;
        }

        const mockupId = $(this).data('mockup-id');
        const $mockupItem = $(this).closest('.mockup-item');

        $.post(fursonaPrintsCoords.ajax_url, {
            action: 'delete_mockup',
            nonce: fursonaPrintsCoords.nonce,
            mockup_id: mockupId
        })
        .done(function(response) {
            if (response.success) {
                $mockupItem.fadeOut(300, function() {
                    $(this).remove();
                    // Show "no mockups" message if list is empty
                    if ($('#mockups-list .mockup-item').length === 0) {
                        $('#mockups-list').html('<p class="no-mockups">No mockups saved yet. Upload and define coordinates to get started.</p>');
                    }
                });
                showMessage(response.data.message, 'success');
            } else {
                showMessage(response.data.message || 'Failed to delete mockup.', 'error');
            }
        })
        .fail(function() {
            showMessage('Network error. Please try again.', 'error');
        });
    }

    /**
     * Show message to user
     */
    function showMessage(message, type) {
        const $messageDiv = $('#coordinate-message');
        $messageDiv
            .removeClass('notice-success notice-error')
            .addClass('notice-' + type)
            .html('<p>' + message + '</p>')
            .slideDown();

        setTimeout(function() {
            $messageDiv.slideUp();
        }, 5000);
    }

    // Update save button state when mockup name changes
    $('#mockup-name').on('input', updateUI);

})(jQuery);
