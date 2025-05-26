<?php
get_header();
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12 col-md-3">
            <?php
            // Get all categories
            $args = array(
                'taxonomy' => 'category', // or your custom taxonomy name
                'orderby' => 'term_order',
                'parent' => 0
            );
            $categories = get_terms( $args ); ?>
            <?php foreach ($categories as $cat) : ?>
                <div id="<?= $cat->slug; ?>" class="parent-category">
                    <?php if ($cat->parent === 0) : ?>
                        <span><?= $cat->name; ?></span>
                        <?php
                        $children = get_term_children($cat->term_id, 'category'); ?>
                        <?php if (!empty($children)) : ?>
                            <ul class="child-categories">
                                <?php foreach ($children as $child) :
                                    $term = get_term_by('term_id', $child, 'category'); ?>
                                    <li id="<?=$term->slug; ?>"><?= $term->name; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <div id="job3" class="job">

            </div>
            <div id="job2" class="job">

            </div>
            <div id="job1" class="job">

            </div>
        </div>
        <div class="col-12 col-md-9">
            <?php
            // Get all projects
            $query = new WP_Query(array(
                'post_status' => 'publish',
            ));
            $projects = $query->posts;

            // Loop through the projects
            if ($projects) :
                foreach ($projects as $project) : ?>
                    <div class="project">
                        <div class="post-content">
                            <?php echo $project->post_content; ?>
                        </div>
                    </div>
                <?php endforeach;
            endif; ?>
        </div>
    </div>
</div>

<?php
get_footer();
?>
