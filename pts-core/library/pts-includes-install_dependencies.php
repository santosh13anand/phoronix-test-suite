<?php

/*
	Phoronix Test Suite
	URLs: http://www.phoronix.com, http://www.phoronix-test-suite.com/
	Copyright (C) 2008 - 2009, Phoronix Media
	Copyright (C) 2008 - 2009, Michael Larabel
	pts-includes-install_dependencies.php: Functions needed for installing external dependencies for PTS.

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

function pts_install_package_on_distribution($identifiers)
{
	// PTS External Dependencies install on distribution
	if(!pts_is_assignment("SILENCE_MESSAGES"))
	{
		echo "Checking For Needed External Dependencies.\n";
	}

	$install_objects = array();
	$identifiers = pts_to_array($identifiers);

	foreach($identifiers as $identifier)
	{
		pts_start_install_dependencies($identifier, $install_objects);
	}
	$install_objects = array_unique($install_objects);

	if(pts_is_assignment("AUTOMATED_MODE") && pts_current_user() != "root")
	{
		return count($install_objects) == 0;
	}

	pts_install_packages_on_distribution_process($install_objects);

	return true;
}
function pts_start_install_dependencies($TO_INSTALL, &$PLACE_LIST)
{
	$tests = pts_contained_tests($TO_INSTALL, true);
	
	foreach($tests as $test)
	{
		if(pts_test_supported($test))
		{
			pts_install_external_dependencies_list($test, $PLACE_LIST);
		}
	}
}
function pts_external_dependency_generic($Name)
{
	// Get the generic information for a PTS External Dependency generic
	$generic_information = "";

	if(is_file(XML_DISTRO_DIR . "generic-packages.xml"))
	{
		$xml_parser = new tandem_XmlReader(XML_DISTRO_DIR . "generic-packages.xml");
		$package_name = $xml_parser->getXMLArrayValues(P_EXDEP_PACKAGE_GENERIC);
		$title = $xml_parser->getXMLArrayValues(P_EXDEP_PACKAGE_TITLE);
		$possible_packages = $xml_parser->getXMLArrayValues(P_EXDEP_PACKAGE_POSSIBLENAMES);
		$file_check = $xml_parser->getXMLArrayValues(P_EXDEP_PACKAGE_FILECHECK);

		$selection = -1;
		$pts_exdep_support = true;

		for($i = 0; $i < count($title) && $selection == -1; $i++)
		{
			if($Name == $package_name[$i])
			{
				$selection = $i;
				if(pts_file_missing_check(explode(",", $file_check[$selection])))
				{
					if($pts_exdep_support)
					{
						$pts_exdep_support = false;
					}

					echo pts_string_header($title[$selection] . "\nPossible Package Names: " . $possible_packages[$selection]);
				}
			}
		}

		if(!$pts_exdep_support)
		{
			echo "The above dependencies should be installed before proceeding. Press any key when you're ready to continue.";
			fgets(STDIN);
		}
	}

	return $generic_information;
}
function pts_install_external_dependencies_list($identifier, &$INSTALL_OBJ)
{
	// Install from a list of external dependencies
	if(!pts_is_test($identifier))
	{
		return;
	}

	$xml_parser = new pts_test_tandem_XmlReader($identifier);
	$title = $xml_parser->getXMLValue(P_TEST_TITLE);
	$dependencies = $xml_parser->getXMLValue(P_TEST_EXDEP);

	if(!empty($dependencies))
	{
		$dependencies = explode(",", $dependencies);

		$dependencies = array_map("trim", $dependencies);

		if(!pts_is_assignment("PTS_EXDEP_FIRST_RUN"))
		{
			array_push($dependencies, "php-extras");

			if(phodevi::read_property("system", "kernel-architecture") == "x86_64")
			{
				array_push($dependencies, "linux-32bit-libraries");
			}

			pts_set_assignment("PTS_EXDEP_FIRST_RUN", 1);
		}

		if(!pts_package_generic_to_distro_name($INSTALL_OBJ, $dependencies))
		{
			$package_string = "";
			foreach($dependencies as $dependency)
			{
				$package_string .= pts_external_dependency_generic($dependency);
			}

			if(!empty($package_string))
			{
				echo "\nSome additional dependencies are required, and they could not be installed automatically for your operating system.\nBelow are the software packages that must be installed for the test(s) to run properly.\n\n" . $package_string;
			}
		}
	}
}
function pts_package_generic_to_distro_name(&$package_install_array, $generic_names)
{
	// Generic name to distribution package name
	$vendor = pts_package_vendor_identifier();
	$generated = false;

	if(is_file(XML_DISTRO_DIR . $vendor . "-packages.xml"))
	{
		$xml_parser = new tandem_XmlReader(XML_DISTRO_DIR . $vendor . "-packages.xml");
		$generic_package = $xml_parser->getXMLArrayValues(P_EXDEP_PACKAGE_GENERIC);
		$distro_package = $xml_parser->getXMLArrayValues(P_EXDEP_PACKAGE_SPECIFIC);
		$file_check = $xml_parser->getXMLArrayValues(P_EXDEP_PACKAGE_FILECHECK);

		for($i = 0; $i < count($generic_package); $i++)
		{
			if(!empty($generic_package[$i]) && ($generic_names == "all" || in_array($generic_package[$i], $generic_names)))
			{
				if(!in_array($distro_package[$i], $package_install_array))
				{
					if(!empty($file_check[$i]))
					{
						$files = explode(",", $file_check[$i]);
						$add_dependency = pts_file_missing_check($files);
					}
					else
					{
						$add_dependency = true;
					}

					if($add_dependency)
					{
						array_push($package_install_array, $distro_package[$i]);
					}
				}
			}
		}
		$generated = true;
	}

	return $generated;
}
function pts_install_packages_on_distribution_process($install_objects)
{
	// Do the actual installing process of packages using the distribution's package management system
	if(!empty($install_objects))
	{
		if(is_array($install_objects))
		{
			$install_objects = implode(" ", $install_objects);
		}

		$distribution = pts_package_vendor_identifier();

		if(is_file(SCRIPT_DISTRO_DIR . "install-" . $distribution . "-packages.sh"))
		{
			echo "\nThe following dependencies will be installed: \n";

			foreach(explode(" ", $install_objects) as $obj)
			{
				echo "- " . $obj . "\n";
			}

			echo "\nThis process may take several minutes.\n";

			echo shell_exec("cd " . SCRIPT_DISTRO_DIR . " && sh install-" . $distribution . "-packages.sh " . $install_objects);
		}
		else
		{
			echo "Distribution install script not found!";
		}
	}
}
function pts_file_missing_check($file_arr)
{
	// Checks if file is missing
	$file_missing = false;

	foreach($file_arr as $file)
	{
		$file_is_there = false;
		$file = explode("OR", $file);

		for($i = 0; $i < count($file) && $file_is_there == false; $i++)
		{
			$file[$i] = trim($file[$i]);

			if(is_file($file[$i]) || is_dir($file[$i]) || is_link($file[$i]))
			{
				$file_is_there = true;
			}
		}
		$file_missing = $file_missing || !$file_is_there;
	}

	return $file_missing;
}
function pts_package_vendor_identifier()
{
	$os_vendor = phodevi::read_property("system", "vendor-identifier");

	if(!is_file(XML_DISTRO_DIR . $os_vendor . "-packages.xml") && !is_file(SCRIPT_DISTRO_DIR . "install-" . $os_vendor . "-packages.sh"))
	{
		if(is_file(STATIC_DIR . "software-vendor-aliases.txt"))
		{
			$vendors_alias_file = trim(file_get_contents(STATIC_DIR . "software-vendor-aliases.txt"));
			$vendors_r = explode("\n", $vendors_alias_file);

			foreach($vendors_r as $vendor)
			{
				$vendor_r = explode("=", $vendor);

				if(count($vendor_r) == 2)
				{
					$to_replace = trim($vendor_r[0]);

					if($os_vendor == $to_replace)
					{
						$os_vendor = trim($vendor_r[1]);
						break;
					}
				}
			}
		}
	}

	return $os_vendor;
}

?>
