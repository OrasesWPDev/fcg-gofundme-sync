<?php get_header() ?>

<div id="banner" class="site-headline">
  <div class="container">
    <p class="h1 headline-title"><?php echo get_queried_object()->name ?>s</p>
  </div>
</div>

<div class="container">
  <div class="row">
    <div id="primary" class="content-area archive-content-area col-12">
      <main id="content" class="site-content">

        <header class="content-header">
          <h1 class="content-title sr-only">Memorials</h1>
        </header>

        <?php if (have_posts()) : ?>
          <div class="row mt-6 mt-lg-4 mb-n6">

            <?php while (have_posts()) : the_post() ?>
              <div class="col-12 col-md-6 col-xl-4 card-type-2-mb">
                <?php
                if (current_user_can('edit_posts')) {
                  echo edit_post_link(get_post_type_object(get_post_type())->labels->edit_item, '', '', $post, 'post-edit-link post-edit-link-fund-category');
                }
                ?>

                <div class="card card-type-2 card-type-2-hover mb-0 h-100">
                  <div class="card-body d-flex flex-column align-items-start">
                    <div class="card-img">
                      <?php
                      $featured_image = get_field('featured_image');
                      if ($featured_image) {
                        echo wp_get_attachment_image($featured_image);
                      } else {
                        echo '<img src="' . get_theme_file_uri('src/images/placeholder-1x1.png') . '">';
                      }
                      ?>
                    </div>

                    <h2 class="h5 card-title"><?php the_title() ?></h2>

                    <a class="btn-link mt-auto" href="<?php the_permalink() ?>">Give Now</a>
                  </div>
                </div>

              </div>
            <?php endwhile ?>

          </div>

        <?php endif ?>

      </main>
    </div>
  </div>
</div>

<?php get_footer() ?>
