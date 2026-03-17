<?php get_header() ?>

<div id="banner" class="site-headline">
  <div class="container">
    <p class="h1 headline-title">Search</p>
  </div>
</div>

<div class="container">
  <div class="row">
    <div id="primary" class="content-area archive-content-area col-12 col-lg-7">
      <main id="content" class="site-content">

        <?php if (get_search_query()) : ?>

          <header class="content-header mb-5">
            <h2 class="content-title">Results<?php echo get_search_query() ? ' for: &ldquo;' . get_search_query() . '&rdquo;' : false ?></h2>
          </header>

          <?php if (have_posts()) : ?>
            <?php while (have_posts()) : the_post() ?>

              <?php if (get_post_type() == 'page') : ?>

                <div class="search-result hentry entry archive-entry">
                  <?php
                  if (current_user_can('edit_posts')) {
                    echo edit_post_link(get_post_type_object(get_post_type())->labels->edit_item, '', '', $post, 'post-edit-link post-edit-link-search-results');
                  }
                  ?>

                  <div class="row">
                    <div class="col col-12 col-sm-3 col-xl-2">
                      <a class="entry-thumbnail" rel="bookmark" href="<?php the_permalink() ?>">
                        <?php
                        if (has_post_thumbnail()) {
                          echo the_post_thumbnail('thumbnail');
                        } else {
                          echo '<img src="' . get_theme_file_uri('src/images/placeholder-1x1.png') . '">';
                        }
                        ?>
                      </a>
                    </div>

                    <div class="col col-12 col-sm-9 col-xl-10">
                      <header class="entry-header">
                        <div class="pretitle font-weight-bold text-secondary">Page</div>

                        <h2 class="h3 entry-title mb-0">
                          <a rel="bookmark" href="<?php the_permalink() ?>"><?php the_title() ?></a>
                        </h2>
                      </header>

                      <div class="entry-summary">
                        <?php echo get_the_excerpt() ?>
                      </div>
                    </div>
                  </div>
                </div>

              <?php elseif (get_post_type() == 'post') : ?>

                <div class="search-result hentry entry archive-entry">
                  <?php
                  if (current_user_can('edit_posts')) {
                    echo edit_post_link(get_post_type_object(get_post_type())->labels->edit_item, '', '', $post, 'post-edit-link post-edit-link-search-results');
                  }
                  ?>

                  <div class="row">
                    <div class="col col-12 col-sm-3 col-xl-2">
                      <a class="entry-thumbnail" rel="bookmark" href="<?php the_permalink() ?>">
                        <?php
                        if (has_post_thumbnail()) {
                          echo the_post_thumbnail('thumbnail');
                        } else {
                          echo '<img src="' . get_theme_file_uri('src/images/placeholder-1x1.png') . '">';
                        }
                        ?>
                      </a>
                    </div>

                    <div class="col col-12 col-sm-9 col-xl-10">
                      <header class="entry-header">
                        <div class="pretitle font-weight-bold text-secondary">News & Stories</div>
                        <h2 class="h3 entry-title mb-0">
                          <a rel="bookmark" href="<?php the_permalink() ?>"><?php the_title() ?></a>
                        </h2>
                      </header>

                      <footer class="entry-footer entry-meta my-2">
                        <span class="date">
                          <?php $date =  strtotime(get_the_date()) ?>
                          <a rel="bookmark" href="/<?php echo date('Y', $date) . '/' . date('m', $date) ?>">
                            <time datetime="<?php echo date('Y-m-d', $date) ?>" class="entry-date">
                              <span class="month"><?php echo date('F', $date) ?></span>
                              <span class="day"><?php echo date('j', $date) ?></span><span class="comma">,</span>
                              <span class="year"><?php echo date('Y', $date) ?></span>
                            </time>
                          </a>
                        </span>

                        <?php $first_category = woodst_get_yoast_sorted_cats()[0] ?>
                        <span class="categories-links"><a rel="category tag" href="/<?php echo $first_category->slug ?>/"><?php echo $first_category->name ?></a></span>
                      </footer>

                      <div class="entry-summary">
                        <?php the_excerpt() ?>
                      </div>
                    </div>
                  </div>
                </div>

              <?php
              elseif (get_post_type() == 'team') :
                $featured_image = get_field('featured_image');
                $role = get_field('role');
                $company = get_field('company');
                $email = get_field('email');
              ?>

                <div class="search-result hentry entry archive-entry">
                  <?php
                  if (current_user_can('edit_posts')) {
                    echo edit_post_link(get_post_type_object(get_post_type())->labels->edit_item, '', '', $post, 'post-edit-link post-edit-link-search-results');
                  }
                  ?>

                  <div class="row">
                    <div class="col col-12 col-sm-3 col-xl-2">
                      <a class="entry-thumbnail" rel="bookmark" href="<?php echo the_permalink() ?>">
                        <?php
                        if ($featured_image) {
                          echo wp_get_attachment_image($featured_image, 'thumbnail');
                        } else {
                          echo '<img src="' . get_theme_file_uri('src/images/placeholder-1x1.png') . '">';
                        }
                        ?>
                      </a>
                    </div>

                    <div class="col col-12 col-sm-9 col-xl-10">
                      <header class="entry-header">
                        <div class="pretitle font-weight-bold text-secondary">Team</div>
                        <h2 class="h3 entry-title mb-0">
                          <a rel="bookmark" href="<?php the_permalink() ?>"><?php the_title() ?></a>
                        </h2>
                      </header>

                      <div class="entry-summary">
                        <?php if ($role) : ?>
                          <p class="mb-0"><?php echo $role ?></p>
                        <?php endif ?>

                        <?php if ($company) : ?>
                          <p><?php echo $company ?></p>
                        <?php endif ?>

                        <?php if ($email) : ?>
                          <a class="btn-link btn-link-sm" href="mailto:<?php echo $email ?>"><span class="icon icon-envelope text-small mr-2"></span>Email</a>
                        <?php endif ?>
                      </div>
                    </div>
                  </div>
                </div>

              <?php elseif (get_post_type() == 'funds') : ?>

                <div class="search-result hentry entry archive-entry">
                  <?php
                  if (current_user_can('edit_posts')) {
                    echo edit_post_link(get_post_type_object(get_post_type())->labels->edit_item, '', '', $post, 'post-edit-link post-edit-link-search-results');
                  }
                  ?>

                  <div class="row">
                    <div class="col col-12 col-sm-3 col-xl-2">
                      <div class="entry-thumbnail" rel="bookmark">
                        <?php
                        if (has_post_thumbnail()) {
                          echo the_post_thumbnail('thumbnail');
                        } else {
                          echo '<img src="' . get_theme_file_uri('src/images/placeholder-1x1.png') . '">';
                        }
                        ?>
                      </div>
                    </div>

                    <div class="col col-12 col-sm-9 col-xl-10">
                      <header class="entry-header">
                        <div class="pretitle font-weight-bold text-secondary">Funds</div>
                        <h2 class="h3 entry-title mb-0">
                          <a rel="bookmark" href="<?php the_permalink() ?>"><?php the_title() ?></a>
                        </h2>
                      </header>

                      <div class="entry-summary">
                        <p><?php echo get_the_excerpt() ?></p>

                        <a class="btn-link" rel="bookmark" href="<?php the_permalink() ?>">Give Now</a>
                      </div>
                    </div>
                  </div>
                </div>

              <?php elseif (get_post_type() == 'publications-reports') : ?>

                <div class="search-result hentry entry archive-entry">
                  <?php
                  if (current_user_can('edit_posts')) {
                    echo edit_post_link(get_post_type_object(get_post_type())->labels->edit_item, '', '', $post, 'post-edit-link post-edit-link-search-results');
                  }
                  ?>

                  <div class="row">
                    <div class="col col-12 col-sm-3 col-xl-2">
                      <div class="entry-thumbnail" rel="bookmark">
                        <?php
                        $featured_image = get_field('featured_image');
                        if ($featured_image) {
                          echo wp_get_attachment_image($featured_image);
                        } else {
                          echo '<img src="' . get_theme_file_uri('src/images/placeholder-1x1.png') . '">';
                        }
                        ?>
                      </div>
                    </div>

                    <div class="col col-12 col-sm-9 col-xl-10">
                      <header class="entry-header">
                        <div class="pretitle font-weight-bold text-secondary">Publications & Reports</div>

                        <h2 class="h3 entry-title mb-0"><?php the_title() ?></h2>
                      </header>

                      <div class="entry-summary">
                        <?php
                        $destination = get_field('destination');
                        $link = get_field('link');
                        $is_download = get_field('is_download');
                        if ($destination == 'content') :
                        ?>
                          <a class="btn-link" rel="bookmark" href="<?php the_permalink() ?>">Read More</a>
                        <?php elseif ($destination == 'link' && $link) :
                          $url = $link['url'];
                          $title = $link['title'];
                          $target = $link['target'] ?: '_self';
                          $download = $is_download ? 'download' : false;
                        ?>
                          <a class="btn-link" href="<?php echo esc_url($url) ?>" target="<?php echo esc_attr($target) ?>" <?php echo $download ?>>
                            <?php echo esc_html($title) ?>
                          </a>
                        <?php endif ?>
                      </div>
                    </div>
                  </div>
                </div>

              <?php endif ?>

            <?php endwhile ?>

            <?php
            global $wp_query;
            if ($wp_query->max_num_pages > 1) :
              $previous_link = str_replace('<a', '<a class="page-numbers previous"', get_previous_posts_link());
              $next_link = str_replace('<a', '<a class="page-numbers next"', get_next_posts_link());

              $links = paginate_links(array(
                'prev_next' => false,
                'type' => 'array',
              ));
            ?>
              <nav class="navigation paging-navigation">
                <div class="nav-links">
                  <div class="nav-links-left">
                    <?php echo $previous_link ?>
                  </div>

                  <div class="nav-links-middle">
                    <?php echo implode($links) ?>
                  </div>

                  <div class="nav-links-right">
                    <?php echo $next_link ?>
                  </div>
                </div>
              </nav>
            <?php endif ?>

          <?php else : ?>

            <header class="entry-header">
              <h1 class="entry-title sr-only">Search</h1>
            </header>

            <div class="entry-content">
              <p class="lead">We could not find any results for your search. You can give it another try through the search form below.</p>

              <div class="row">
                <div class="col-12 col-lg-6">
                  <form class="search-form" method="get" action="/" role="search">
                    <div class="input-group input-group-lg">
                      <input type="search" class="search-field form-control" value="" name="s" />

                      <div class="input-group-append">
                        <button type="submit" class="btn btn-primary search-submit">
                          <span class="sr-only">Search</span>
                          <span class="icon icon-search"></span>
                        </button>
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </div>

          <?php endif ?>

        <?php else : ?>

          <header class="entry-header">
            <h1 class="entry-title sr-only">Search</h1>
          </header>

          <div class="entry-content">
            <p class="lead">Enter your search terms in the form below.</p>

            <div class="row">
              <div class="col-12 col-lg-6">
                <form class="search-form" method="get" action="/" role="search">
                  <div class="input-group input-group-lg">
                    <input type="search" class="search-field form-control" value="" name="s" />

                    <div class="input-group-append">
                      <button type="submit" class="btn btn-primary search-submit">
                        <span class="sr-only">Search</span>
                        <span class="icon icon-search"></span>
                      </button>
                    </div>
                  </div>
                </form>
              </div>
            </div>
          </div>

        <?php endif ?>

      </main>
    </div>

    <div id="secondary" class="sidebar-area col-12 col-lg-4 offset-lg-1">
      <aside id="sidebar" class="site-sidebar">

        <?php
        /**
         * Sidebar Menu
         */

        $sidebar_menu = get_field('sidebar_menu', get_option('page_for_posts'));

        if ($sidebar_menu) :
        ?>
          <nav class="navigation sidebar-navigation">
            <h2 class="sidebar-navigation-title sr-only">Sidebar Navigation</h2>

            <?php
            wp_nav_menu(
              array(
                'container' => false,
                'menu_class' => 'menu sidebar-menu',
                'menu' => $sidebar_menu
              )
            );
            ?>

          </nav>
        <?php endif ?>

        <nav class="navigation archives-navigation">
          <h2 class="archives-navigation-title">Archives</h2>
          <select id="archives-dropdown" name="archives-dropdown" class="archives-dropdown form-control" onChange="document.location.href=this.options[this.selectedIndex].value;">
            <option value="<?php echo get_the_permalink(get_option('page_for_posts')) ?>">Select</option>

            <?php
            wp_get_archives(array(
              'format' => 'option',
              'limit' => 12
            ));
            ?>
          </select>
        </nav>

        <?php
        /**
         * Sidebar Callouts
         */

        get_template_part('sidebar-callouts');
        ?>

      </aside>
    </div>
  </div>
</div>

<?php get_footer() ?>
