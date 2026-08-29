<?php
/**
 * Intelligence Brief - Key Players
 * Page 5 of 12
 */

if (!defined('ABSPATH')) {
    exit;
}

$players = sffc_brief_get($brief, 'key_players', array());
$relationship_description = sffc_brief_get($brief, 'key_players_relationship', '');

// Organize players by role
$organized_players = array(
    'target' => array(),
    'acquirer' => array(),
    'buyer' => array(),
    'seller' => array(),
    'investor' => array(),
    'advisor' => array(),
    'other' => array(),
);

foreach ($players as $player) {
    $role = strtolower(sffc_brief_get($player, 'role', 'other'));
    if (isset($organized_players[$role])) {
        $organized_players[$role][] = $player;
    } else {
        $organized_players['other'][] = $player;
    }
}

// Role display labels
$role_labels = array(
    'target' => 'Target Company',
    'acquirer' => 'Acquirer',
    'buyer' => 'Buyer',
    'seller' => 'Seller',
    'investor' => 'Investor',
    'advisor' => 'Advisor',
    'other' => 'Key Party',
);
?>

<div class="page">
    <div class="page-header">
        <svg class="logo-mark" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="8" cy="8" r="4" fill="#1E6B4A"/>
            <path d="M8 16C8 16 4 20 4 28C4 32 6 36 12 36C18 36 28 36 32 32C36 28 36 20 32 16C28 12 20 12 16 16C12 20 8 16 8 16Z" fill="#1E6B4A"/>
        </svg>
        <div class="page-number">5</div>
    </div>

    <div class="page-content">
        <div class="section-box">
            <h2>Key Players</h2>
        </div>

        <?php if ($relationship_description) : ?>
        <p class="mb-lg"><?php echo esc_html($relationship_description); ?></p>
        <?php endif; ?>

        <div class="players-grid">
            <?php
            $displayed = 0;
            $row_open = false;

            foreach ($organized_players as $role => $role_players) :
                foreach ($role_players as $player) :
                    if ($displayed % 2 === 0) :
                        if ($row_open) echo '</div>';
                        echo '<div class="players-row">';
                        $row_open = true;
                    endif;
                    $displayed++;

                    $player_name = sffc_brief_get($player, 'name', '');
                    $player_description = sffc_brief_get($player, 'description', '');
                    $player_type = sffc_brief_get($player, 'type', '');
                    $player_country = sffc_brief_get($player, 'country', '');
            ?>
                    <div class="player-card">
                        <div class="player-role"><?php echo esc_html(isset($role_labels[$role]) ? $role_labels[$role] : ucfirst($role)); ?></div>
                        <div class="player-name"><?php echo esc_html($player_name); ?></div>
                        <?php if ($player_type || $player_country) : ?>
                        <div style="font-size: 10pt; color: var(--color-text-light); margin-bottom: 6pt;">
                            <?php
                            $meta = array();
                            if ($player_type) $meta[] = ucfirst(str_replace('_', ' ', $player_type));
                            if ($player_country) $meta[] = $player_country;
                            echo esc_html(implode(' | ', $meta));
                            ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($player_description) : ?>
                        <div class="player-description"><?php echo esc_html($player_description); ?></div>
                        <?php endif; ?>
                    </div>
            <?php
                endforeach;
            endforeach;

            // Close any open row and fill empty cell if odd number
            if ($row_open) :
                if ($displayed % 2 === 1) :
                    echo '<div class="player-card" style="border: none; background: transparent;"></div>';
                endif;
                echo '</div>';
            endif;
            ?>
        </div>

        <?php
        // Show advisors separately if they exist
        $advisors = sffc_brief_get($brief, 'advisors', array());
        if (!empty($advisors)) :
        ?>
        <div class="mt-lg">
            <h4 class="heading-section mb-md" style="font-size: 10pt;">Transaction Advisors</h4>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Firm</th>
                        <th>Role</th>
                        <th>Representing</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($advisors as $advisor) : ?>
                    <tr>
                        <td><strong><?php echo esc_html(sffc_brief_get($advisor, 'name', '')); ?></strong></td>
                        <td><?php echo esc_html(sffc_brief_get($advisor, 'role', '')); ?></td>
                        <td><?php echo esc_html(sffc_brief_get($advisor, 'representing', '')); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="page-footer">
        <div class="footer-brand">MENA Careers Finance</div>
        <div class="footer-confidential">Intelligence Brief</div>
    </div>
</div>
