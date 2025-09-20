/**
 * -------------------------------------------------------------------------
 * Derived from Metabase plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is based on Metabase.
 *
 * Metabase is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Metabase is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Metabase. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2018-2023 by Metabase plugin team.
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/pluginsGLPI/metabase
 * -------------------------------------------------------------------------
 * Modified by Grafana plugin team
 * @copyright Copyright (C) 2025 by Grafana plugin team.
 * @link      https://github.com/Open-Sa/grafana 
 * -------------------------------------------------------------------------
 * Changes:
 * - Added code for clipboard copy button
 */

$(function() {

   // do like a jquery toggle but based on a parameter
   $.fn.toggleFromValue = function(val) {
      if (val === 1
          || val === "1"
          || val === true) {
         this.show();
      } else {
         this.hide();
      }
   };

   $(document).on("click", ".grafana_folder_list label", function() {
      $(this).toggleClass('expanded');
   });

   $(document).on("click", "a.extract", function() {
      var uid = $(this).data('uid');
      var type = $(this).data('type');
      glpi_ajax_dialog({
         dialogclass: 'modal-lg',
         url: CFG_GLPI.root_doc + '/' + GLPI_PLUGINS_PATH.grafana + '/ajax/extract_json.php',
         params: {
            uid: uid,
            type: type
         }
      });
   });

   $(document).on('click', '#copy_clipboard', function() {
      navigator.clipboard.writeText(document.getElementById("grafana_jwks_url").textContent)
         .then(() => {
            var range = document.createRange();
            var selection = window.getSelection();
            range.selectNodeContents(document.getElementById("grafana_jwks_url"));
            selection.removeAllRanges();
            selection.addRange(range);

            copied_text = document.getElementById("translated_copied").value;
            copy_text = document.getElementById("translated_copy").value;


            document.getElementById("button_text").innerText = copied_text;
            document.getElementById("button_icon").className = "ti ti-check";
            document.getElementById("copy_clipboard").classList.add("btn-success");

            setTimeout(() => {
               document.getElementById("button_text").innerText = copy_text;
               document.getElementById("button_icon").className = "ti ti-clipboard";
               document.getElementById("copy_clipboard").classList.remove("btn-success");
               selection.removeAllRanges();
            }, 4000);

         })
         .catch(err => {
            console.error('Error copying to clipboard: ', err);
            document.getElementById("button_text").innerText = "Error";
            document.getElementById("button_icon").className = "ti ti-face-sad";
         });
   });


});
