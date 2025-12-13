(function($) {
    class PhotologPhoto {
        previous = null;
        next = null;
        constructor(container, photo, previous = null) {
            if (previous == null) {
                this.index = 0;
            } else {
                this.index = previous.index + 1;
            }
            this.container = container;
            this.photo = photo;
            this.filename = photo.file_name;
            this.thumbname = photo.thumb_200;
            this.timestamp = photo.timestamp;
            this.loaded = false;
            this.previous = previous;
            this.next = null;
            if (previous) {
                this.previous.next = this;
            }
            this.img = null;
            this.thumb = null;
            this.highres = false;
            this.lowres = false;
            this.visible = false;
        }
        load() {
            if (!this.loaded) {
                this.loaded = true;
                var self = this;
                // console.log("preloading " + this.thumbname);

                this.thumb = new Image(); // Create a new Image object
                this.thumb.fetchPriority = "high"; // Set the priority to 'high'
                this.thumb.loading = "eager";
                this.thumb.classList.add("hidden");
                this.thumb.addEventListener("load", function(e) {
                    // console.log("preloading " + self.filename);

                    self.lowres = true;
                    const start = Date.now();
                    self.img = new Image(); // Create a new Image object
                    self.img.loading = "eager";
                    self.img.classList.add("hidden");
                    self.img.addEventListener("load", function(e) {
                        const end = Date.now();
                        const duration = end - start;
                        // console.log("it took " + duration + "ms to load hires image");
                        self.highres = true;
                        if (self.visible) {
                            self.hide();
                            self.show();
                        }
                    });
                    self.img.src = self.filename;     // Setting the src triggers the browser to fetch the image
                    self.container.appendChild(self.img);
                });
                this.thumb.src = this.thumbname;     // Setting the src triggers the browser to fetch the image
                this.container.appendChild(this.thumb);


            }
        }
        show() {
            if(this.highres) {
                this.img.classList.remove("hidden");
            } else {
                this.thumb.classList.remove("hidden");
            }
            this.visible = true;
        }
        hide() {
            this.img.classList.add("hidden");
            this.thumb.classList.add("hidden");
            this.visible = false;
        }
    }
    class PhotologTimelapse {
        constructor(id, data, playSpeed = 1000, bufferSize = 16) {
            var photos = data.photos;
            this.id = id;
            this.head = null;
            this.tail = null;
            this.timerange = data.timerange;
            this.length = photos.length;
            this.playSpeed = playSpeed;
            this.photos = photos;
            this.bufferSize = bufferSize;
            this.length = photos.length
            this.container = document.getElementById(this.id);
            this.playerWindow = this.container.parentElement;
            this.seekBar = this.playerWindow.getElementsByClassName("seek-bar")[0];
            this.cursor = this.seekBar.getElementsByClassName("cursor")[0];
            this.cursorbar = this.seekBar.getElementsByClassName("cursorbar")[0];
            this.caption = this.playerWindow.getElementsByClassName("timelapse-caption")[0];
            this.photolog = [];
            var player = this;
            this.registerButtons();

            if (this.length > 0) {
                var previous = null;
                for (let i = 0; i < photos.length; i++) {
                    var node = new PhotologPhoto(this.container, photos[i], previous);
                    this.photolog.push(node);
                    const tick = document.createElement('div');
                    tick.classList.add("tick");
                    tick.style = "left:"+(node.photo.timeline_pos)+"%";
                    this.seekBar.appendChild(tick);
                    previous = node;
                    if (this.head == null) {
                        this.head = node;
                    }
                    this.tail = node;
                }
                // make it a loop
                this.tail.next = this.head;
                this.head.previous = this.tail;
            } else { 
                this.caption.innerHTML = "No photos available";
            }
            this.seekBar.addEventListener("click", function(event) {

                // 3. Get the div's position relative to the viewport
                const rect = player.seekBar.getBoundingClientRect();

                // 4. Calculate the relative x and y coordinates
                const x = event.clientX - rect.left; // x position within the element
                const y = event.clientY - rect.top;  // y position within the element

                // 5. Display the coordinates (or use them as needed)
                // const xPos = Math.round(x);
                // const yPos = Math.round(y);
                const timeline_pos = 100*x/rect.width;
                var dist = 200;
                for (let i = 0; i < player.photolog.length; i++) {
                    const photoDist = Math.abs(player.photolog[i].photo.timeline_pos - timeline_pos);
                    if (photoDist <= dist) {
                        dist = photoDist;
                    } else if (i > 0) {
                        player.skip(i-1);
                        return;
                    } else {
                        player.skip(0);
                        return;
                    }
                }
                if (player.photolog.length > 0) {
                    player.skip(player.photolog.length - 1);
                }

            });
        }

        registerButtons() {
            var playPauseButton = this.playerWindow.getElementsByClassName("play-button")[0];
            var player = this;
            playPauseButton.addEventListener("click", function(e) {
                player.togglePlayPause();
            });
            const nav = this.playerWindow.getElementsByClassName("nav")[0];
            nav.getElementsByClassName("next")[0].addEventListener("click", function () {
                player.forward();
            });
            nav.getElementsByClassName("previous")[0].addEventListener("click", function () {
                player.back();
            });
            nav.getElementsByClassName("start")[0].addEventListener("click", function () {
                player.skip(0);
            });
            nav.getElementsByClassName("end")[0].addEventListener("click", function () {
                if (player.photolog.length > 0) {
                    player.skip(player.photolog.length - 1);
                }
            });
            nav.getElementsByClassName("rate-increase")[0].addEventListener("click", function() {
                player.faster();
            });
            nav.getElementsByClassName("rate-decrease")[0].addEventListener("click", function() {
                player.slower();
            });
        }

        buffer() {
            // load initial buffer
            if (this.head) {
                var node = this.head;
                for (let i = 0; i < this.bufferSize; i++) {
                    node.load();
                    node = node.next;
                    this.tail = node;
                }
                this.head.show();
                this.caption.innerHTML = this.head.timestamp;
            }
        }
        forward() {
            if (!this.head) {
                return;
            }
            if (!this.head.next.lowres) {
                return;
            }
            let prev = this.head;
            this.head = this.head.next;
            this.caption.innerHTML = this.head.timestamp;
            this.tail.load();
            this.tail = this.tail.next;
            this.head.show();
            this.cursor.style = "left:"+(this.head.photo.timeline_pos)+"%";
            this.cursorbar.style = "width:"+(this.head.photo.timeline_pos)+"%";
            prev.hide();
        }
        back() {
            if (!this.head) {
                return;
            }
            this.head.hide();
            this.head = this.head.previous;
            this.head.load();
            this.caption.innerHTML = this.head.timestamp;
            this.tail = this.tail.previous;
            this.head.show();
            this.cursor.style = "left:"+(this.head.photo.timeline_pos)+"%";
            this.cursorbar.style = "width:"+(this.head.photo.timeline_pos)+"%";
        }
        play(moveForward = true) {
            if (!this.head) {
                return;
            }
            if (moveForward) {
                this.forward();
            }
            if (!this.interval) {
                var player = this;
                this.interval = setInterval(function() {
                    player.forward();
                }, this.playSpeed);
                this.playerWindow.classList.add("playing");

            }
        }
        skip(toIndex) {
            if (!this.head) {
                return;
            }
            this.head.hide();
            this.head = this.photolog[toIndex];
            this.tail = this.head;
            this.cursor.style = "left:"+(this.head.photo.timeline_pos)+"%";
            this.cursorbar.style = "width:"+(this.head.photo.timeline_pos)+"%";
            this.buffer();
        }
        pause() {
            if (this.interval) {
                var player = this;
                clearInterval(this.interval);
                this.interval = null;
                this.playerWindow.classList.remove("playing");
            }
        }
        faster() {
            if(this.playSpeed > 100) {
                this.playSpeed -= 100;
                this.pause();
                this.play();
            }
        }
        slower() {
            this.playSpeed += 100;
            this.pause();
            this.play(false);
        }
        togglePlayPause() {
            if (!this.head) {
                return;
            }
            if (this.interval) {
                this.pause();
            } else {
                this.play();
            }
        }
    }

    function fppPreloadImg(name) {
        let img = new Image();
        img.src = name;
    }

    $(document).ready(function() {
        fppPreloadImg('/wp-content/plugins/photolog/assets/pause.svg');
        fppPreloadImg('/wp-content/plugins/photolog/assets/condense.svg');

        $("[id^=timelapse-player-]").each(function(index, element) {
            var id = $(this).attr("id");
            var data = window[id.replaceAll("-", "_")];
            var player = new PhotologTimelapse($(this).attr("id"), data);
            player.buffer();
            // player.play();
        });
        $(".sizing-button").click(function() {
            var player = $(this).closest(".timelapse-player");
            if (player.hasClass("four-by-three")) {
                $(this).closest(".timelapse-player").removeClass("four-by-three", 500).addClass("two-by-one", 500);
            } else {
                $(this).closest(".timelapse-player").removeClass("two-by-one", 500).addClass("four-by-three", 500);
            }
        });
        $(".timelapse-player").hover(
            function () {
                $(this).find(".timelapse-header").removeClass("hidden");
                $(this).find(".timelapse-controls").removeClass("hidden");
            }, function () {
                $(this).find(".timelapse-controls").addClass("hidden");
                $(this).find(".timelapse-header").addClass("hidden");
        });
        $(".timelapse-player [tooltip]").hover(
            function() {
                // console.log($(this).closest(".timelapse-player").find(".help-text").html());
                $(this).closest(".timelapse-player").find(".help-text").html($(this).attr("tooltip"));
            },
            function() {
                $(this).closest(".timelapse-player").find(".help-text").html("");
            }
        );
        var modal = new PhotologModal("#fpp-carousel-info");
        $(".timelapse-player a.info-link").click(function() {
            // alert("clicked");
            modal.show();
        });
    });
})(jQuery);