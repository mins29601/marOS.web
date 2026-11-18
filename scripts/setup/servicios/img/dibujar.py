W,H=1600,1100
GOLD="#A67C36"; DARK="#8A6528"; BG="#F3EFE9"; DISC="#E5DFD5"; INK="#252728"
def wrap(inner):
    return f'''<svg xmlns="http://www.w3.org/2000/svg" width="{W}" height="{H}" viewBox="0 0 {W} {H}">
<rect width="{W}" height="{H}" fill="{BG}"/>
<circle cx="800" cy="560" r="390" fill="{DISC}"/>
<g fill="none" stroke="{GOLD}" stroke-width="10" stroke-linecap="round" stroke-linejoin="round">{inner}</g>
</svg>'''
transport = '''
<path d="M300 760 H1300" stroke="#C9B48E"/>
<path d="M420 820 H560 M700 820 H900 M1040 820 H1180" stroke="#C9B48E" stroke-width="6"/>
<path d="M470 700 C470 650 500 630 540 625 L620 620 L700 545 C720 525 745 515 780 515 L960 515 C1000 515 1025 530 1050 560 L1110 625 L1160 635 C1200 643 1220 665 1220 700 L1220 715 C1220 725 1212 732 1202 732 L488 732 C478 732 470 725 470 715 Z" fill="#FBFBFB"/>
<path d="M650 620 L725 555 C735 547 748 543 762 543 L850 543 L850 620 Z M880 543 L955 543 C975 543 990 552 1003 568 L1045 620 L880 620 Z" fill="#E5DFD5"/>
<circle cx="610" cy="735" r="55" fill="#FBFBFB"/><circle cx="610" cy="735" r="20"/>
<circle cx="1080" cy="735" r="55" fill="#FBFBFB"/><circle cx="1080" cy="735" r="20"/>
<path d="M1190 665 H1215 M480 668 H505"/>
<circle cx="1150" cy="330" r="55" stroke-width="8"/>
<path d="M1150 245 V225 M1150 435 V415 M1065 330 H1045 M1255 330 H1235" stroke-width="6"/>
'''
concierge = '''
<rect x="560" y="700" width="480" height="70" rx="12" fill="#FBFBFB"/>
<path d="M610 700 C610 560 690 500 800 500 C910 500 990 560 990 700 Z" fill="#FBFBFB"/>
<path d="M800 500 V445" stroke-width="12"/>
<path d="M760 440 H840" stroke-width="16"/>
<path d="M670 660 C680 600 720 560 775 545" stroke="#C9B48E" stroke-width="8"/>
<path d="M600 735 H1000" stroke="#C9B48E" stroke-width="5"/>
<path d="M880 400 l30 -40 M930 430 l45 -20 M720 400 l-30 -40" stroke-width="7"/>
<path d="M1150 470 l0 56 M1122 498 l56 0" stroke-width="6"/>
<path d="M455 560 l0 40 M435 580 l40 0" stroke-width="5"/>
'''
chef = '''
<path d="M690 520 C610 520 590 420 660 390 C670 320 760 300 800 345 C840 300 930 320 940 390 C1010 420 990 520 910 520 Z" fill="#FBFBFB"/>
<path d="M705 520 V600 H895 V520" fill="#FBFBFB"/>
<path d="M705 570 H895" stroke="#C9B48E" stroke-width="7"/>
<ellipse cx="800" cy="745" rx="250" ry="48" fill="#FBFBFB"/>
<ellipse cx="800" cy="738" rx="160" ry="24" stroke="#C9B48E" stroke-width="6"/>
<path d="M455 640 V850 M430 640 V700 C430 720 480 720 480 700 V640"/>
<path d="M1145 640 C1185 660 1185 740 1145 760 V850"/>
'''
events = '''
<path d="M640 430 L720 430 L705 560 C700 600 660 600 655 560 Z" fill="#FBFBFB" transform="rotate(-14 680 600)"/>
<path d="M680 600 V770 M635 775 H725" transform="rotate(-14 680 600)"/>
<path d="M880 430 L960 430 L945 560 C940 600 900 600 895 560 Z" fill="#FBFBFB" transform="rotate(14 920 600)"/>
<path d="M920 600 V770 M875 775 H965" transform="rotate(14 920 600)"/>
<path d="M800 300 V350 M745 320 L770 360 M855 320 L830 360" stroke-width="7"/>
<circle cx="560" cy="330" r="10" fill="#A67C36" stroke="none"/><circle cx="1040" cy="300" r="12" fill="#A67C36" stroke="none"/>
<circle cx="1110" cy="460" r="8" fill="#A67C36" stroke="none"/><circle cx="490" cy="480" r="8" fill="#A67C36" stroke="none"/>
<path d="M380 240 C560 300 1040 300 1220 240" stroke="#C9B48E" stroke-width="5"/>
<circle cx="500" cy="272" r="9" fill="#E5DFD5"/><circle cx="650" cy="290" r="9" fill="#E5DFD5"/><circle cx="800" cy="295" r="9" fill="#E5DFD5"/><circle cx="950" cy="290" r="9" fill="#E5DFD5"/><circle cx="1100" cy="272" r="9" fill="#E5DFD5"/>
'''
for n,i in dict(transport=transport,concierge=concierge,catering=chef,events=events).items():
    open(f"{n}.svg","w").write(wrap(i))
